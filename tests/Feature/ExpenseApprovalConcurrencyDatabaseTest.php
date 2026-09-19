<?php

namespace Tests\Feature;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Services\Finance\ExpenseApprovalService;
use App\Services\Identity\ExpenseApprovalReadiness;
use App\Support\Auditing\AuditContext;
use App\Support\Auditing\AuditLogger;
use Closure;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;
use Throwable;

class ExpenseApprovalConcurrencyDatabaseTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    #[Test]
    public function ac_8_two_simultaneous_approvals_from_same_account_count_only_once_under_database_lock(): void
    {
        $this->requireMysqlOrMariaDbProof();
        $this->requireProcessForking();
        $this->seed(RolePermissionSeeder::class);

        $directionRole = Role::findByName('direction');
        $approvePermission = Permission::findByName('depense.approuver');
        $existingDirectionAssignments = $this->pivotRows('model_has_roles', 'role_id', (int) $directionRole->getKey());
        $existingDirectAssignments = $this->pivotRows('model_has_permissions', 'permission_id', (int) $approvePermission->getKey());
        DB::table('model_has_roles')->where('role_id', $directionRole->getKey())->delete();
        DB::table('model_has_permissions')->where('permission_id', $approvePermission->getKey())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $firstDirection = User::factory()->active()->withRole('direction')->create();
        $secondDirection = User::factory()->active()->withRole('direction')->create();
        $requester = User::factory()->active()->withRole('employe')->create();
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()
            ->requested()
            ->for($requester, 'requester')
            ->for($category, 'category')
            ->create();
        $createdUserIds = [$firstDirection->getKey(), $secondDirection->getKey(), $requester->getKey()];
        $createdPersonIds = [$firstDirection->person_id, $secondDirection->person_id, $requester->person_id];
        $temporaryDirectory = sys_get_temp_dir().'/staffptr-expense-approval-'.bin2hex(random_bytes(8));

        if (! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Impossible de créer la barrière temporaire du test de concurrence.');
        }

        $lockAcquiredPath = $temporaryDirectory.'/lock-acquired';
        $contenderStartedPath = $temporaryDirectory.'/contender-started';
        $resultPaths = [$temporaryDirectory.'/result-0.json', $temporaryDirectory.'/result-1.json'];
        $pids = [];

        try {
            DB::disconnect();

            for ($contender = 0; $contender < 2; $contender++) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new RuntimeException("Impossible de créer le processus concurrent {$contender}.");
                }

                if ($pid === 0) {
                    $this->runConcurrentApproval(
                        contender: $contender,
                        expenseId: (int) $expense->getKey(),
                        approverId: (int) $firstDirection->getKey(),
                        lockAcquiredPath: $lockAcquiredPath,
                        contenderStartedPath: $contenderStartedPath,
                        resultPath: $resultPaths[$contender],
                    );
                }

                $pids[] = $pid;
            }

            $exitCodes = [];

            foreach ($pids as $pid) {
                $processStatus = 0;
                pcntl_waitpid($pid, $processStatus);
                $exitCodes[] = pcntl_wifexited($processStatus) ? pcntl_wexitstatus($processStatus) : 255;
            }

            DB::purge();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $results = array_map(fn (string $path): array => $this->readResult($path), $resultPaths);
            $statuses = array_column($results, 'status');
            sort($statuses);

            $this->assertSame([0, 0], $exitCodes, 'Les deux processus doivent terminer sans erreur technique.');
            $this->assertSame(['success', 'validation'], $statuses);
            $this->assertSame(
                1,
                ExpenseApproval::query()
                    ->where('expense_id', $expense->getKey())
                    ->where('approver_id', $firstDirection->getKey())
                    ->count(),
            );
            $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);

            $validationResult = collect($results)->firstWhere('status', 'validation');
            $this->assertIsArray($validationResult);
            $this->assertGreaterThanOrEqual(
                200,
                $validationResult['elapsed_ms'],
                'Le second processus doit attendre le verrou détenu par la première transaction.',
            );
            $this->assertTrue(
                collect($validationResult['queries'])
                    ->contains(static fn (string $sql): bool => str_contains($sql, 'for update')),
                'Le processus concurrent doit émettre SELECT ... FOR UPDATE.',
            );
            $this->assertSame(
                1,
                DB::table('audit_logs')
                    ->where('auditable_type', Expense::class)
                    ->where('auditable_id', $expense->getKey())
                    ->where('action', 'expense_approved')
                    ->count(),
            );
        } finally {
            foreach ($pids as $pid) {
                $status = 0;
                pcntl_waitpid($pid, $status, WNOHANG);
            }

            DB::purge();
            $this->cleanupFixtures(
                expenseId: (int) $expense->getKey(),
                categoryId: (int) $category->getKey(),
                userIds: array_map('intval', $createdUserIds),
                personIds: array_map('intval', $createdPersonIds),
                existingDirectionAssignments: $existingDirectionAssignments,
                existingDirectAssignments: $existingDirectAssignments,
            );

            foreach ($resultPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            foreach ([$lockAcquiredPath, $contenderStartedPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            if (is_dir($temporaryDirectory)) {
                rmdir($temporaryDirectory);
            }
        }
    }

    private function requireProcessForking(): void
    {
        if (function_exists('pcntl_fork') && function_exists('pcntl_waitpid')) {
            return;
        }

        if (config('app.ci')) {
            $this->fail('La CI MySQL doit fournir PCNTL pour la preuve de concurrence AC 8.');
        }

        $this->markTestSkipped('PCNTL est requis pour la preuve de concurrence MySQL AC 8.');
    }

    private function requireMysqlOrMariaDbProof(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (config('app.ci')) {
            $this->fail('La preuve de concurrence AC 8 doit s’exécuter sous MySQL ou MariaDB en CI.');
        }

        $this->markTestSkipped('Preuve de concurrence AC 8 réservée à MySQL/MariaDB.');
    }

    private function runConcurrentApproval(
        int $contender,
        int $expenseId,
        int $approverId,
        string $lockAcquiredPath,
        string $contenderStartedPath,
        string $resultPath,
    ): never {
        DB::purge();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = mb_strtolower($query->sql);
        });
        $startedAt = hrtime(true);
        $status = 'error';
        $message = null;

        try {
            $expense = Expense::query()->findOrFail($expenseId);
            $approver = User::query()->findOrFail($approverId);

            if ($contender === 0) {
                $logger = new BlockingExpenseAuditLogger(
                    app(AuditContext::class),
                    $lockAcquiredPath,
                    $contenderStartedPath,
                );
                $service = new ExpenseApprovalService($logger, app(ExpenseApprovalReadiness::class));
                $service->approve($expense, $approver);
            } else {
                $this->waitForFile($lockAcquiredPath);
                file_put_contents($contenderStartedPath, 'ready', LOCK_EX);
                app(ExpenseApprovalService::class)->approve($expense, $approver);
            }

            $status = 'success';
        } catch (ValidationException $exception) {
            $status = 'validation';
            $message = $exception->errors()['approval'][0] ?? $exception->getMessage();
        } catch (Throwable $exception) {
            $message = $exception::class.': '.$exception->getMessage();
        }

        file_put_contents($resultPath, json_encode([
            'status' => $status,
            'message' => $message,
            'elapsed_ms' => (hrtime(true) - $startedAt) / 1_000_000,
            'queries' => $queries,
        ], JSON_THROW_ON_ERROR), LOCK_EX);

        exit($status === 'error' ? 1 : 0);
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 5;

        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException("Barrière de concurrence non atteinte : {$path}");
            }

            usleep(10_000);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pivotRows(string $table, string $key, int $identifier): array
    {
        return DB::table($table)
            ->where($key, $identifier)
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @return array{status: string, message: string|null, elapsed_ms: float, queries: list<string>}
     */
    private function readResult(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Résultat concurrent absent : {$path}");
        }

        $result = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($result)
            || ! is_string($result['status'] ?? null)
            || ! is_numeric($result['elapsed_ms'] ?? null)
            || ! is_array($result['queries'] ?? null)) {
            throw new RuntimeException("Résultat concurrent invalide : {$path}");
        }

        return [
            'status' => $result['status'],
            'message' => is_string($result['message'] ?? null) ? $result['message'] : null,
            'elapsed_ms' => (float) $result['elapsed_ms'],
            'queries' => array_values(array_filter(
                $result['queries'],
                static fn (mixed $query): bool => is_string($query),
            )),
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $personIds
     * @param  list<array<string, mixed>>  $existingDirectionAssignments
     * @param  list<array<string, mixed>>  $existingDirectAssignments
     */
    private function cleanupFixtures(
        int $expenseId,
        int $categoryId,
        array $userIds,
        array $personIds,
        array $existingDirectionAssignments,
        array $existingDirectAssignments,
    ): void {
        $connection = DB::connection($this->migrationConnectionName());
        $connection->table('model_has_roles')->whereIn('model_id', $userIds)->delete();
        $connection->table('model_has_permissions')->whereIn('model_id', $userIds)->delete();
        $connection->table('expense_approvals')->where('expense_id', $expenseId)->delete();
        $connection->table('expenses')->where('id', $expenseId)->delete();
        $connection->table('expense_categories')->where('id', $categoryId)->delete();
        $connection->table('users')->whereIn('id', $userIds)->delete();
        $connection->table('people')->whereIn('id', $personIds)->delete();

        if ($existingDirectionAssignments !== []) {
            $connection->table('model_has_roles')->insertOrIgnore($existingDirectionAssignments);
        }

        if ($existingDirectAssignments !== []) {
            $connection->table('model_has_permissions')->insertOrIgnore($existingDirectAssignments);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

class BlockingExpenseAuditLogger extends AuditLogger
{
    public function __construct(
        AuditContext $context,
        private readonly string $lockAcquiredPath,
        private readonly string $contenderStartedPath,
    ) {
        parent::__construct($context);
    }

    public function runExplicitly(
        Model $auditable,
        Closure $operation,
        ?int $actorId,
        string $actorLabel,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): mixed {
        file_put_contents($this->lockAcquiredPath, 'locked', LOCK_EX);
        $this->waitForContender();
        usleep(300_000);

        return parent::runExplicitly(
            auditable: $auditable,
            operation: $operation,
            actorId: $actorId,
            actorLabel: $actorLabel,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            reason: $reason,
            requestId: $requestId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    private function waitForContender(): void
    {
        $deadline = microtime(true) + 5;

        while (! is_file($this->contenderStartedPath)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Le second processus n’a pas tenté son approbation à temps.');
            }

            usleep(10_000);
        }
    }
}
