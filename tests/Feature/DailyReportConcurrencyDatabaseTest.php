<?php

namespace Tests\Feature;

use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Accountability\AccountabilityDashboardService;
use App\Services\Accountability\DailyReportService;
use App\Services\Platform\AttachmentService;
use App\Services\Platform\CalendarService;
use App\Services\Platform\SettingsService;
use App\Services\Work\TodayTaskService;
use App\Support\Auditing\AuditContext;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;
use Throwable;

class DailyReportConcurrencyDatabaseTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    #[Test]
    public function ac_1_9_and_22_concurrent_identical_submissions_keep_one_identity_and_one_version(): void
    {
        $this->requireConcurrencyEnvironment();
        // Ce test n'utilise pas RefreshDatabase : l'heure limite et les jours travaillés
        // doivent exister en base avant que les processus concurrents ne démarrent.
        $this->seed(SettingSeeder::class);
        $actor = User::factory()->active()->create();
        $directory = sys_get_temp_dir().'/staffptr-daily-report-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Impossible de créer la barrière du test de rapport quotidien.');
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
                    throw new RuntimeException('Impossible de créer le processus concurrent de rapport.');
                }

                if ($pid === 0) {
                    $this->runContender(
                        $contender,
                        (int) $actor->getKey(),
                        $lockPath,
                        $startedPath,
                        $resultPaths[$contender],
                    );
                }

                $pids[] = $pid;
            }

            foreach ($pids as $pid) {
                $status = 0;
                pcntl_waitpid($pid, $status);
                $this->assertTrue(
                    pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
                    'Un processus concurrent de rapport a échoué techniquement.',
                );
            }

            DB::purge();
            $results = array_map(fn (string $path): array => $this->readResult($path), $resultPaths);

            $this->assertSame(['success', 'success'], array_column($results, 'status'));
            $this->assertSame(1, DailyReport::query()->where('author_id', $actor->getKey())->whereDate('report_date', '2026-08-11')->count());
            $report = DailyReport::query()->where('author_id', $actor->getKey())->whereDate('report_date', '2026-08-11')->sole();
            $this->assertSame(1, $report->versions()->count());
            $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', DailyReport::class)->where('auditable_id', $report->getKey())->where('action', 'daily_report_submitted')->count());
        } finally {
            foreach ($pids as $pid) {
                $status = 0;
                pcntl_waitpid($pid, $status, WNOHANG);
            }

            DB::purge();
            $this->cleanup((int) $actor->getKey(), (int) $actor->person_id);

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

    private function runContender(
        int $contender,
        int $actorId,
        string $lockPath,
        string $startedPath,
        string $resultPath,
    ): never {
        DB::purge();
        $status = 'error';
        $message = null;

        try {
            $actor = User::query()->findOrFail($actorId);
            $logger = $contender === 0
                ? new BlockingDailyReportAuditLogger(app(AuditContext::class), $lockPath, $startedPath)
                : app(AuditLogger::class);

            if ($contender === 1) {
                $this->waitForFile($lockPath);
                file_put_contents($startedPath, 'ready', LOCK_EX);
            }

            $this->service($logger)->submit(
                $this->data(),
                $actor,
                CarbonImmutable::parse('2026-08-11 16:00:00', 'Africa/Niamey'),
            );
            $status = 'success';
        } catch (Throwable $exception) {
            $message = $exception::class.': '.$exception->getMessage();
        }

        file_put_contents($resultPath, json_encode([
            'status' => $status,
            'message' => $message,
        ], JSON_THROW_ON_ERROR), LOCK_EX);

        exit($status === 'success' ? 0 : 1);
    }

    private function service(AuditLogger $logger): DailyReportService
    {
        return new DailyReportService(
            $logger,
            app(AccountabilityDashboardService::class),
            app(AttachmentService::class),
            app(CalendarService::class),
            app(SettingsService::class),
            app(TodayTaskService::class),
        );
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return [
            'planned_task' => 'Préparer la démonstration',
            'achieved_result' => 'Démonstration préparée',
            'evidence_link' => 'https://example.test/preuve',
            'blocker_present' => false,
            'next_action' => 'Présenter la démonstration',
            'help_requested' => false,
            'idempotency_key' => 'bb04bb0f-b382-4d8d-9329-9c29fc3ce3cf',
        ];
    }

    /** @return array{status: string, message: string|null} */
    private function readResult(string $path): array
    {
        $result = is_file($path)
            ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)
            : null;

        if (! is_array($result) || ! is_string($result['status'] ?? null)) {
            throw new RuntimeException('Résultat concurrent de rapport absent ou invalide.');
        }

        return [
            'status' => $result['status'],
            'message' => is_string($result['message'] ?? null) ? $result['message'] : null,
        ];
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

    private function requireConcurrencyEnvironment(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            if (config('app.ci')) {
                $this->fail('La concurrence des rapports quotidiens doit être prouvée sous MySQL/MariaDB en CI.');
            }

            $this->markTestSkipped('Preuve de concurrence des rapports réservée à MySQL/MariaDB.');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            $this->markTestSkipped('PCNTL est requis pour la preuve concurrente des rapports.');
        }
    }

    private function cleanup(int $actorId, int $personId): void
    {
        $connection = DB::connection($this->migrationConnectionName());
        $reportIds = $connection->table('daily_reports')->where('author_id', $actorId)->pluck('id');
        // Le journal d'audit est en ajout seul (déclencheurs MySQL) : on ne le purge jamais,
        // aucune clé étrangère ne le relie aux comptes supprimés ici.
        $connection->table('daily_report_versions')->whereIn('daily_report_id', $reportIds)->delete();
        $connection->table('daily_reports')->whereIn('id', $reportIds)->delete();
        $connection->table('model_has_roles')->where('model_id', $actorId)->delete();
        $connection->table('model_has_permissions')->where('model_id', $actorId)->delete();
        $connection->table('users')->where('id', $actorId)->delete();
        $connection->table('people')->where('id', $personId)->delete();
    }
}

class BlockingDailyReportAuditLogger extends AuditLogger
{
    public function __construct(
        AuditContext $context,
        private readonly string $lockPath,
        private readonly string $startedPath,
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
        file_put_contents($this->lockPath, 'locked', LOCK_EX);
        $deadline = microtime(true) + 5;

        while (! is_file($this->startedPath)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Le second envoi n’a pas atteint la barrière.');
            }

            usleep(10_000);
        }

        usleep(300_000);

        return parent::record(
            $actorId,
            $actorLabel,
            $auditable,
            $action,
            $oldValues,
            $newValues,
            $reason,
            $requestId,
            $ipAddress,
            $userAgent,
        );
    }
}
