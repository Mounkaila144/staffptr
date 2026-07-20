<?php

namespace Tests\Feature;

use App\Console\Commands\CreateFirstAdmin;
use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Support\Auditing\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\IdentityTestCase;

class FirstAdminBootstrapTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_2_4_5_6_7_and_8_command_creates_the_first_admin_securely(): void
    {
        $auditCountBefore = AuditLog::query()->count();
        $lastAuditId = (int) (AuditLog::query()->max('id') ?? 0);
        [$exitCode, $output] = $this->runCommand('Aïcha Amadou', '90 00 11 22');

        $this->assertSame(Command::SUCCESS, $exitCode, $output);
        $this->assertMatchesRegularExpression(
            '/Mot de passe temporaire — affiché une seule fois : ([a-f0-9]{32})/',
            $output,
        );
        preg_match('/Mot de passe temporaire — affiché une seule fois : ([a-f0-9]{32})/', $output, $matches);
        $temporaryPassword = $matches[1];
        $user = User::query()->sole();
        $person = Person::query()->sole();

        $this->assertSame(32, strlen($temporaryPassword));
        $this->assertSame(1, substr_count($output, $temporaryPassword));
        $this->assertNotSame($temporaryPassword, $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check($temporaryPassword, $user->password));
        $this->assertSame('Aïcha Amadou', $person->full_name);
        $this->assertSame(PersonOperationalStatus::Actif, $person->operational_status);
        $this->assertSame('+22790001122', $user->phone);
        $this->assertSame(UserState::Actif, $user->state);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(['super_admin'], $user->getRoleNames()->all());
        $this->assertStringContainsString('ne détient aucune permission métier', $output);
        $this->assertStringContainsString('créer les deux comptes direction', $output);

        $audits = AuditLog::query()
            ->where('id', '>', $lastAuditId)
            ->orderBy('id')
            ->get();
        $this->assertCount(3, $audits);
        $this->assertSame($auditCountBefore + 3, AuditLog::query()->count());
        $this->assertSame(
            ['person_created', 'user_created', 'user_roles_changed'],
            $audits->pluck('action')->all(),
        );
        $this->assertSame(['Amorçage système'], $audits->pluck('actor_label')->unique()->values()->all());
        $this->assertTrue($audits->every(static fn (AuditLog $audit): bool => $audit->actor_id === null));

        $serializedAudit = $audits
            ->map(static fn (AuditLog $audit): string => json_encode([
                $audit->old_values,
                $audit->new_values,
            ], JSON_THROW_ON_ERROR))
            ->implode('\n');
        $this->assertStringNotContainsString($temporaryPassword, $serializedAudit);
        $this->assertStringNotContainsString((string) $user->getRawOriginal('password'), $serializedAudit);
        $this->assertStringNotContainsStringIgnoringCase('password', $serializedAudit);
    }

    public function test_ac_3_archived_account_refuses_before_questions_and_writes_nothing(): void
    {
        User::factory()->archived()->create();
        $before = [
            Person::query()->count(),
            User::query()->count(),
            AuditLog::query()->count(),
        ];

        $command = app(CreateFirstAdmin::class);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('un compte existe déjà', $tester->getDisplay());
        $this->assertSame($before, [
            Person::query()->count(),
            User::query()->count(),
            AuditLog::query()->count(),
        ]);
    }

    public function test_ac_4_signature_has_no_password_argument_or_option(): void
    {
        $command = app(CreateFirstAdmin::class);
        $definition = $command->getDefinition();
        $surface = collect([
            ...array_map(
                static fn ($argument): string => $argument->getName().' '.($argument->getDescription() ?? ''),
                $definition->getArguments(),
            ),
            ...array_map(
                static fn ($option): string => $option->getName().' '.($option->getDescription() ?? ''),
                $definition->getOptions(),
            ),
        ])->implode(' ');

        $this->assertSame('ptr:create-first-admin', $command->getName());
        $this->assertDoesNotMatchRegularExpression('/password|mot\s+de\s+passe/i', $surface);
    }

    public function test_ac_7_audit_failure_rolls_back_the_whole_bootstrap(): void
    {
        $auditCountBefore = AuditLog::query()->count();
        $delegate = app(AuditLogger::class);
        $this->app->instance(AuditLogger::class, new FailingThirdAuditLogger($delegate));

        [$exitCode, $output] = $this->runCommand('Compte annulé', '90 00 11 23');

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString("Aucune donnée partielle n'a été conservée", $output);
        $this->assertSame(0, Person::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame($auditCountBefore, AuditLog::query()->count());
    }

    /** @return array{int, string} */
    private function runCommand(string $fullName, string $phone): array
    {
        $command = app(CreateFirstAdmin::class);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->setInputs([$fullName, $phone]);
        $exitCode = $tester->execute([]);

        return [$exitCode, $tester->getDisplay()];
    }
}

class FailingThirdAuditLogger extends AuditLogger
{
    private int $callCount = 0;

    public function __construct(private readonly AuditLogger $delegate) {}

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
        $result = $this->delegate->runExplicitly(
            $auditable,
            $operation,
            $actorId,
            $actorLabel,
            $action,
            $oldValues,
            $newValues,
            $reason,
            $requestId,
            $ipAddress,
            $userAgent,
        );

        $this->callCount++;

        if ($this->callCount === 3) {
            throw new RuntimeException("Échec d'audit forcé.");
        }

        return $result;
    }
}
