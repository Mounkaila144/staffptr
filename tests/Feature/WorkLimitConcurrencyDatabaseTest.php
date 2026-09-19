<?php

namespace Tests\Feature;

use App\Enums\CompanyPriorityState;
use App\Enums\ObjectiveState;
use App\Enums\WorkPriority;
use App\Models\Identity\User;
use App\Models\Work\CompanyPriority;
use App\Models\Work\Objective;
use App\Services\Platform\AttachmentService;
use App\Services\Work\CompanyPriorityService;
use App\Services\Work\ObjectiveService;
use App\Services\Work\WorkDashboardService;
use App\Support\Auditing\AuditContext;
use App\Support\Auditing\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;
use Throwable;

class WorkLimitConcurrencyDatabaseTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    #[Test]
    public function ac_2_two_concurrent_creations_never_persist_a_sixth_company_priority(): void
    {
        $this->requireConcurrencyEnvironment();
        $actor = User::factory()->active()->create();

        CompanyPriority::factory()->count(4)->for($actor, 'owner')->create([
            'month' => '2026-07-01',
            'due_date' => '2026-07-31',
            'state' => CompanyPriorityState::Validee,
        ]);

        $results = $this->race('priority', (int) $actor->getKey());

        $this->assertRaceResult($results);
        $this->assertSame(5, CompanyPriority::query()->whereDate('month', '2026-07-01')->where('state', CompanyPriorityState::Validee)->count());
        $this->cleanup([$actor]);
    }

    #[Test]
    public function ac_9_two_concurrent_validations_never_make_a_fourth_monthly_objective_official(): void
    {
        $this->requireConcurrencyEnvironment();
        $owner = User::factory()->active()->create();
        Objective::factory()->count(2)->for($owner, 'owner')->create([
            'created_by' => $owner->getKey(),
            'due_date' => '2026-07-31',
            'state' => ObjectiveState::Valide,
        ]);
        Objective::factory()->count(2)->for($owner, 'owner')->create([
            'created_by' => $owner->getKey(),
            'due_date' => '2026-07-31',
            'state' => ObjectiveState::Brouillon,
        ]);

        $results = $this->race('objective', (int) $owner->getKey());

        $this->assertRaceResult($results);
        $this->assertSame(3, Objective::query()->where('user_id', $owner->getKey())->whereIn('state', [ObjectiveState::Valide, ObjectiveState::EnCours, ObjectiveState::Atteint, ObjectiveState::PartiellementAtteint, ObjectiveState::NonAtteint, ObjectiveState::Bloque])->count());
        $this->cleanup([$owner]);
    }

    /** @return list<array{status: string, message: string|null}> */
    private function race(string $kind, int $actorId): array
    {
        $directory = sys_get_temp_dir().'/staffptr-work-limit-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Impossible de créer la barrière du test Work.');
        }
        $lockPath = $directory.'/lock';
        $startedPath = $directory.'/started';
        $resultPaths = [$directory.'/result-0.json', $directory.'/result-1.json'];
        $pids = [];

        try {
            DB::disconnect();
            foreach ([0, 1] as $contender) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new RuntimeException('Impossible de créer le processus concurrent Work.');
                }
                if ($pid === 0) {
                    $this->runContender($kind, $contender, $actorId, $lockPath, $startedPath, $resultPaths[$contender]);
                }
                $pids[] = $pid;
            }
            foreach ($pids as $pid) {
                $status = 0;
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'Un processus concurrent Work a échoué techniquement.');
            }
            DB::purge();

            return array_map(fn (string $path): array => $this->readResult($path), $resultPaths);
        } finally {
            foreach ($pids as $pid) {
                $status = 0;
                pcntl_waitpid($pid, $status, WNOHANG);
            }
            DB::purge();
            foreach ([...$resultPaths, $lockPath, $startedPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function runContender(string $kind, int $contender, int $actorId, string $lockPath, string $startedPath, string $resultPath): never
    {
        DB::purge();
        $status = 'error';
        $message = null;

        try {
            $actor = User::query()->findOrFail($actorId);
            if ($contender === 0) {
                $logger = new BlockingWorkAuditLogger(app(AuditContext::class), $lockPath, $startedPath);
            } else {
                $this->waitForFile($lockPath);
                file_put_contents($startedPath, 'ready', LOCK_EX);
                $logger = app(AuditLogger::class);
            }

            if ($kind === 'priority') {
                $service = new CompanyPriorityService($logger);
                $service->create($this->priorityData($actor, "Concurrent {$contender}"), $actor);
            } else {
                $objective = Objective::query()->where('user_id', $actorId)->where('state', ObjectiveState::Brouillon)->orderBy('id')->skip($contender)->firstOrFail();
                $service = new ObjectiveService($logger, app(AttachmentService::class), app(WorkDashboardService::class));
                $service->validate($objective, $actor);
            }
            $status = 'success';
        } catch (ValidationException $exception) {
            $status = 'validation';
            $message = $exception->getMessage();
        } catch (Throwable $exception) {
            $message = $exception::class.': '.$exception->getMessage();
        }

        file_put_contents($resultPath, json_encode(['status' => $status, 'message' => $message], JSON_THROW_ON_ERROR), LOCK_EX);
        exit($status === 'error' ? 1 : 0);
    }

    /** @param list<array{status: string, message: string|null}> $results */
    private function assertRaceResult(array $results): void
    {
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame(['success', 'validation'], $statuses);
    }

    /** @return array{status: string, message: string|null} */
    private function readResult(string $path): array
    {
        $result = is_file($path) ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : null;
        if (! is_array($result) || ! is_string($result['status'] ?? null)) {
            throw new RuntimeException('Résultat concurrent Work absent ou invalide.');
        }

        return ['status' => $result['status'], 'message' => is_string($result['message'] ?? null) ? $result['message'] : null];
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 5;
        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Barrière de concurrence Work non atteinte.');
            }
            usleep(10_000);
        }
    }

    private function requireConcurrencyEnvironment(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            if (config('app.ci')) {
                $this->fail('Les limites Work concurrentes doivent être prouvées sous MySQL/MariaDB en CI.');
            }
            $this->markTestSkipped('Preuve de concurrence Work réservée à MySQL/MariaDB.');
        }
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            $this->markTestSkipped('PCNTL est requis pour la preuve de concurrence Work.');
        }
    }

    /** @param list<User> $users */
    private function cleanup(array $users): void
    {
        $userIds = array_map(static fn (User $user): int => (int) $user->getKey(), $users);
        $personIds = array_map(static fn (User $user): int => (int) $user->person_id, $users);
        $connection = DB::connection($this->migrationConnectionName());
        // Le journal d'audit est en ajout seul (déclencheurs MySQL) : on ne le purge jamais,
        // aucune clé étrangère ne le relie aux comptes supprimés ici.
        $connection->table('objectives')->whereIn('user_id', $userIds)->delete();
        $connection->table('company_priorities')->whereIn('owner_id', $userIds)->delete();
        $connection->table('model_has_roles')->whereIn('model_id', $userIds)->delete();
        $connection->table('model_has_permissions')->whereIn('model_id', $userIds)->delete();
        $connection->table('users')->whereIn('id', $userIds)->delete();
        $connection->table('people')->whereIn('id', $personIds)->delete();
    }

    /** @return array<string, mixed> */
    private function priorityData(User $owner, string $title): array
    {
        return ['month' => '2026-07-01', 'title' => $title, 'description' => 'Priorité concurrente.', 'owner_id' => $owner->getKey(), 'indicator' => 'Taux', 'target' => '100 %', 'due_date' => '2026-07-31', 'priority' => WorkPriority::Haute->value];
    }
}

class BlockingWorkAuditLogger extends AuditLogger
{
    public function __construct(AuditContext $context, private readonly string $lockPath, private readonly string $startedPath)
    {
        parent::__construct($context);
    }

    public function runExplicitly(Model $auditable, Closure $operation, ?int $actorId, string $actorLabel, string $action, ?array $oldValues = null, ?array $newValues = null, ?string $reason = null, ?string $requestId = null, ?string $ipAddress = null, ?string $userAgent = null): mixed
    {
        file_put_contents($this->lockPath, 'locked', LOCK_EX);
        $deadline = microtime(true) + 5;
        while (! is_file($this->startedPath)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Le second processus Work n’a pas atteint la barrière.');
            }
            usleep(10_000);
        }
        usleep(300_000);

        return parent::runExplicitly($auditable, $operation, $actorId, $actorLabel, $action, $oldValues, $newValues, $reason, $requestId, $ipAddress, $userAgent);
    }
}
