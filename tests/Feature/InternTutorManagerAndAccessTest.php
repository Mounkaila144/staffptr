<?php

namespace Tests\Feature;

use App\Enums\InternshipState;
use App\Enums\ObjectiveState;
use App\Enums\UserState;
use App\Models\Accountability\Internship;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Work\Objective;
use App\Services\Accountability\InternshipIntakeService;
use App\Services\Accountability\InternshipService;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Décisions du propriétaire du 2026-11-01 :
 *  - 2 : le tuteur devient le responsable direct (`manager_id`) du stagiaire ;
 *  - 5 : un stage terminé ferme l'accès d'un compte qui n'a que le rôle stagiaire.
 */
class InternTutorManagerAndAccessTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_activation_makes_the_tutor_the_intern_manager_with_audit_and_history(): void
    {
        [$direction, $tutor, $candidate] = $this->activated();

        $this->assertSame($tutor->getKey(), $candidate->refresh()->manager_id);

        $audit = AuditLog::query()->where('action', 'manager_changed')->where('auditable_id', $candidate->getKey())->sole();
        $this->assertSame($direction->getKey(), $audit->actor_id);
        $this->assertSame($tutor->getKey(), $audit->new_values['manager_id']);
        $this->assertDatabaseHas('user_history', ['user_id' => $candidate->getKey(), 'field' => 'manager_id']);
    }

    public function test_the_tutor_sees_validates_and_moves_the_intern_objectives(): void
    {
        [, $tutor, $candidate] = $this->activated();
        $objective = Objective::query()->where('user_id', $candidate->getKey())->firstOrFail();

        $this->actingAs($tutor)
            ->get(route('objectives.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('objectives.data', fn (Collection $items): bool => $items->contains('id', $objective->getKey())));

        $this->actingAs($tutor)->get(route('objectives.show', $objective))->assertOk();

        $this->actingAs($tutor)
            ->patch(route('objectives.validate', $objective))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(ObjectiveState::Valide, $objective->refresh()->state);

        $this->actingAs($tutor)
            ->patch(route('objectives.transition', $objective), ['state' => ObjectiveState::EnCours->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(ObjectiveState::EnCours, $objective->refresh()->state);

        // Un autre tuteur, sans lien avec ce stagiaire, n'y a toujours pas accès.
        $otherTutor = User::factory()->active()->withRole('tuteur')->create();
        $this->flushSession();
        $this->actingAs($otherTutor)->get(route('objectives.show', $objective))->assertForbidden();
    }

    public function test_changing_the_tutor_realigns_the_intern_manager(): void
    {
        [$direction, , $candidate] = $this->activated();
        $internship = Internship::query()->where('user_id', $candidate->getKey())->sole();
        $newTutor = User::factory()->active()->withRole('tuteur')->create();

        app(InternshipService::class)->assignTutor($internship, $newTutor, $direction);

        $this->assertSame($newTutor->getKey(), $candidate->refresh()->manager_id);
        $this->assertSame(2, AuditLog::query()->where('action', 'manager_changed')->where('auditable_id', $candidate->getKey())->count());
    }

    public function test_the_alignment_command_is_idempotent_and_honours_dry_run(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $oldManager = User::factory()->active()->withRole('direction')->create();
        $intern = User::factory()->active()->withRole('stagiaire')->create(['manager_id' => $oldManager->getKey()]);
        Internship::factory()->forTutor($tutor)->create(['user_id' => $intern->getKey()]);
        $alreadyAligned = User::factory()->active()->withRole('stagiaire')->create(['manager_id' => $tutor->getKey()]);
        Internship::factory()->forTutor($tutor)->create(['user_id' => $alreadyAligned->getKey()]);
        $endedIntern = User::factory()->active()->withRole('stagiaire')->create();
        Internship::factory()->forTutor($tutor)->create(['user_id' => $endedIntern->getKey(), 'state' => InternshipState::Termine]);

        $this->artisan('ptr:align-intern-managers', ['--dry-run' => true])
            ->expectsOutputToContain('1 stagiaire(s) à aligner')
            ->assertSuccessful();
        $this->assertSame($oldManager->getKey(), $intern->refresh()->manager_id);
        $this->assertSame(0, AuditLog::query()->where('action', 'manager_changed')->count());

        $this->artisan('ptr:align-intern-managers')
            ->expectsOutputToContain('1 stagiaire(s) aligné(s)')
            ->assertSuccessful();
        $this->assertSame($tutor->getKey(), $intern->refresh()->manager_id);
        $this->assertNull($endedIntern->refresh()->manager_id);
        $audit = AuditLog::query()->where('action', 'manager_changed')->sole();
        $this->assertNull($audit->actor_id);
        $this->assertSame('Commande ptr:align-intern-managers', $audit->actor_label);

        $this->artisan('ptr:align-intern-managers')
            ->expectsOutputToContain('0 stagiaire(s) aligné(s)')
            ->assertSuccessful();
        $this->assertSame(1, AuditLog::query()->where('action', 'manager_changed')->count());
    }

    public function test_ending_the_internship_closes_a_pure_intern_account_and_revokes_sessions(): void
    {
        [$tutor, $intern, $internship] = $this->runningInternship();
        $this->openSession($intern);

        $this->actingAs($tutor)
            ->patch(route('internships.exit', $internship))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'accès du stagiaire à l’application est fermé'));

        $this->assertSame(InternshipState::Termine, $internship->refresh()->state);
        $this->assertSame(UserState::Termine, $intern->refresh()->state);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $intern->getKey())->count());

        $closed = AuditLog::query()->where('action', 'intern_access_closed')->sole();
        $this->assertSame(1, $closed->new_values['sessions_revoked']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_state_changed', 'auditable_id' => $intern->getKey()]);

        $this->actingAs($tutor)
            ->get(route('internships.show', $internship))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('internship.intern_access_closed', true));

        // Le compte fermé ne passe plus : l'intercepteur le déconnecte à la requête suivante.
        $this->flushSession();
        $this->actingAs($intern)->get(route('internships.show', $internship))->assertRedirect(route('login'));
    }

    public function test_ending_the_internship_keeps_access_when_the_intern_has_another_role(): void
    {
        [$tutor, $intern, $internship] = $this->runningInternship();
        $intern->assignRole('employe');

        $this->actingAs($tutor)
            ->patch(route('internships.exit', $internship))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Le compte reste actif'));

        $this->assertSame(InternshipState::Termine, $internship->refresh()->state);
        $this->assertSame(UserState::Actif, $intern->refresh()->state);
        $this->assertSame(0, AuditLog::query()->where('action', 'intern_access_closed')->count());

        $this->actingAs($tutor)
            ->get(route('internships.show', $internship))
            ->assertInertia(fn (Assert $page): Assert => $page->where('internship.intern_access_closed', false));
    }

    /** @return array{0: User, 1: User, 2: User} */
    private function activated(): array
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $candidate = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);
        $intake = app(InternshipIntakeService::class);
        $form = $intake->draft($direction, [
            'candidate_user_id' => (int) $candidate->getKey(),
            'manager_id' => (int) $direction->getKey(),
            'tutor_id' => (int) $tutor->getKey(),
            'real_need' => 'Renfort sur la documentation.',
            'mission' => 'Rédiger les procédures du module de suivi.',
            'duration_weeks' => 12,
            'tools' => 'Poste de travail.',
            'outcomes' => ['Résultat un.', 'Résultat deux.', 'Résultat trois.'],
        ]);
        $intake->decide($intake->submit($form, $direction), $direction, approved: true);
        Objective::factory()->count(3)->create(['user_id' => $candidate->getKey(), 'created_by' => $direction->getKey()]);

        app(InternshipService::class)->activate($candidate, $direction);

        return [$direction, $tutor, $candidate];
    }

    /** @return array{0: User, 1: User, 2: Internship} */
    private function runningInternship(): array
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $intern = User::factory()->active()->withRole('stagiaire')->create(['manager_id' => $tutor->getKey()]);
        $internship = Internship::factory()->forTutor($tutor)->create(['user_id' => $intern->getKey()]);

        return [$tutor, $intern, $internship];
    }

    private function openSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'session-stagiaire-'.$user->getKey(),
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }
}
