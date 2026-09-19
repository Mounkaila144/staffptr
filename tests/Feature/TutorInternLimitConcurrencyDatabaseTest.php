<?php

namespace Tests\Feature;

use App\Models\Accountability\Internship;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Accountability\InternshipService;
use App\Services\Accountability\TutorCapacityService;
use App\Services\Identity\IdentityService;
use App\Services\Identity\InternActivationReadiness;
use App\Support\Auditing\AuditContext;
use App\Support\Auditing\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;
use Throwable;

/**
 * AC 26 — le contrôle de la limite est protégé contre la concurrence : deux affectations
 * simultanées vers le même tuteur ne peuvent pas dépasser la limite.
 *
 * La preuve exige un vrai `SELECT ... FOR UPDATE`, que SQLite n'applique pas (DEC-02) : le test
 * ne s'exécute que sous MySQL ou MariaDB, et échoue franchement si la CI ne les fournit pas.
 */
class TutorInternLimitConcurrencyDatabaseTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    #[Test]
    public function ac_26_two_simultaneous_assignments_cannot_exceed_the_tutor_limit_under_database_lock(): void
    {
        $this->requireMysqlOrMariaDbProof();
        $this->requireProcessForking();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $otherTutor = User::factory()->active()->withRole('tuteur')->create();
        $actor = User::factory()->active()->withRole('direction')->create();

        // Le tuteur occupe 2 places sur 3 : une seule des deux affectations doit passer.
        $existing = [];
        for ($index = 0; $index < 2; $index++) {
            $existing[] = Internship::factory()->forTutor($tutor)->create();
        }

        $first = Internship::factory()->forTutor($otherTutor)->create();
        $second = Internship::factory()->forTutor($otherTutor)->create();

        $temporaryDirectory = sys_get_temp_dir().'/staffptr-tutor-limit-'.bin2hex(random_bytes(8));

        if (! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Impossible de créer la barrière temporaire du test de concurrence.');
        }

        $lockAcquiredPath = $temporaryDirectory.'/lock-acquired';
        $contenderStartedPath = $temporaryDirectory.'/contender-started';
        $resultPaths = [$temporaryDirectory.'/result-0.json', $temporaryDirectory.'/result-1.json'];
        $internshipIds = [(int) $first->getKey(), (int) $second->getKey()];
        $pids = [];

        try {
            DB::disconnect();

            for ($contender = 0; $contender < 2; $contender++) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new RuntimeException("Impossible de créer le processus concurrent {$contender}.");
                }

                if ($pid === 0) {
                    $this->runConcurrentAssignment(
                        contender: $contender,
                        internshipId: $internshipIds[$contender],
                        tutorId: (int) $tutor->getKey(),
                        actorId: (int) $actor->getKey(),
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
            $this->assertSame(['success', 'validation'], $statuses, 'Une seule affectation doit aboutir.');

            // La garantie centrale : la limite de 3 n'est jamais franchie.
            $this->assertSame(
                3,
                Internship::query()->where('tutor_id', $tutor->getKey())->occupyingTutorSlot()->count(),
                'Le tuteur ne doit jamais encadrer plus de 3 stagiaires actifs.',
            );

            $refused = collect($results)->firstWhere('status', 'validation');
            $this->assertIsArray($refused);
            $this->assertStringContainsString('soit la limite en vigueur', (string) $refused['message']);
            $this->assertGreaterThanOrEqual(
                200,
                $refused['elapsed_ms'],
                'Le second processus doit attendre le verrou détenu par la première transaction.',
            );
            $this->assertTrue(
                collect($refused['queries'])
                    ->contains(static fn (string $sql): bool => str_contains($sql, 'for update')),
                'Le processus concurrent doit émettre SELECT ... FOR UPDATE.',
            );
        } finally {
            foreach ($pids as $pid) {
                $status = 0;
                pcntl_waitpid($pid, $status, WNOHANG);
            }

            DB::purge();
            $this->cleanupFixtures(
                internshipIds: array_merge(
                    $internshipIds,
                    array_map(static fn (Internship $internship): int => (int) $internship->getKey(), $existing),
                ),
            );

            foreach (array_merge($resultPaths, [$lockAcquiredPath, $contenderStartedPath]) as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            if (is_dir($temporaryDirectory)) {
                rmdir($temporaryDirectory);
            }
        }
    }

    private function runConcurrentAssignment(
        int $contender,
        int $internshipId,
        int $tutorId,
        int $actorId,
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
            $internship = Internship::query()->findOrFail($internshipId);
            $tutor = User::query()->findOrFail($tutorId);
            $actor = User::query()->findOrFail($actorId);

            if ($contender === 0) {
                $service = new InternshipService(
                    new BlockingTutorAssignmentAuditLogger(
                        app(AuditContext::class),
                        $lockAcquiredPath,
                        $contenderStartedPath,
                    ),
                    app(IdentityService::class),
                    app(InternActivationReadiness::class),
                    app(TutorCapacityService::class),
                );
                $service->assignTutor($internship, $tutor, $actor);
            } else {
                $this->waitForFile($lockAcquiredPath);
                file_put_contents($contenderStartedPath, 'ready', LOCK_EX);
                app(InternshipService::class)->assignTutor($internship, $tutor, $actor);
            }

            $status = 'success';
        } catch (ValidationException $exception) {
            $status = 'validation';
            $message = $exception->errors()['tutor_id'][0] ?? $exception->getMessage();
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

    private function requireProcessForking(): void
    {
        if (function_exists('pcntl_fork') && function_exists('pcntl_waitpid')) {
            return;
        }

        if (config('app.ci')) {
            $this->fail('La CI MySQL doit fournir PCNTL pour la preuve de concurrence AC 26.');
        }

        $this->markTestSkipped('PCNTL est requis pour la preuve de concurrence MySQL AC 26.');
    }

    private function requireMysqlOrMariaDbProof(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (config('app.ci')) {
            $this->fail('La preuve de concurrence AC 26 doit s’exécuter sous MySQL ou MariaDB en CI.');
        }

        $this->markTestSkipped('Preuve de concurrence AC 26 réservée à MySQL/MariaDB.');
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
     * @param  list<int>  $internshipIds
     */
    private function cleanupFixtures(array $internshipIds): void
    {
        DB::table('audit_logs')
            ->where('auditable_type', Internship::class)
            ->whereIn('auditable_id', $internshipIds)
            ->delete();
        DB::table('internships')->whereIn('id', $internshipIds)->delete();
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
                static fn (mixed $sql): bool => is_string($sql),
            )),
        ];
    }
}

/**
 * Retient la transaction du premier processus après la prise du verrou sur la ligne du tuteur,
 * le temps que le second processus tente son affectation.
 */
class BlockingTutorAssignmentAuditLogger extends AuditLogger
{
    public function __construct(
        AuditContext $context,
        private readonly string $lockAcquiredPath,
        private readonly string $contenderStartedPath,
    ) {
        parent::__construct($context);
    }

    public function record(
        ?int $actorId,
        string $actorLabel,
        Model $auditable,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        file_put_contents($this->lockAcquiredPath, 'locked', LOCK_EX);
        $this->waitForContender();
        usleep(300_000);

        return parent::record(
            actorId: $actorId,
            actorLabel: $actorLabel,
            auditable: $auditable,
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
                throw new RuntimeException('Le second processus n’a pas tenté son affectation à temps.');
            }

            usleep(10_000);
        }
    }
}
