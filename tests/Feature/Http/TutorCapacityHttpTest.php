<?php

namespace Tests\Feature\Http;

use App\Enums\UserState;
use App\Models\Accountability\Internship;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Accountability\InternshipIntakeService;
use Database\Seeders\SettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 5 — écran de gestion de la charge et refus serveur de l'affectation (AC 20, 21, 25, 40).
 */
class TutorCapacityHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /**
     * AC 25 : chaque tuteur apparaît avec sa charge, et celui qui a atteint la limite porte un
     * libellé explicite.
     */
    public function test_ac_25_the_management_screen_shows_each_load_and_labels_the_saturated_tutor(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $full = $this->tutor('Moussa Idrissa');
        Internship::factory()->count(3)->forTutor($full)->create();

        $response = $this->actingAs($direction)
            ->get(route('tutors.capacity'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Accountability/Internships/Tutors/Index')
                ->where('limit', 3)
                ->has('tutors'));

        /** @var list<array<string, mixed>> $tutors */
        $tutors = $response->viewData('page')['props']['tutors'];
        $saturated = collect($tutors)->firstWhere('name', 'Moussa Idrissa');

        $this->assertIsArray($saturated);
        $this->assertSame(3, $saturated['active_interns']);
        $this->assertTrue($saturated['at_limit']);
        $this->assertSame('Limite atteinte', $saturated['limit_label']);
        $this->assertSame('3 stagiaires sur 3', $saturated['load_label']);

        // Un associé sans stagiaire figure aussi dans l'écran, sans libellé de limite.
        $associate = collect($tutors)->firstWhere('id', $direction->getKey());
        $this->assertIsArray($associate);
        $this->assertSame(0, $associate['active_interns']);
        $this->assertFalse($associate['at_limit']);
        $this->assertNull($associate['limit_label']);
    }

    public function test_the_capacity_screen_is_refused_to_an_account_that_does_not_manage_interns(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();

        $this->actingAs($employee)->get(route('tutors.capacity'))->assertForbidden();
    }

    /**
     * AC 20, 21 et 40 : l'activation d'un quatrième stagiaire chez un tuteur saturé est refusée
     * par le serveur, et le message nomme le tuteur et sa charge.
     */
    public function test_ac_20_activation_is_refused_when_the_tutor_is_already_at_the_limit(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $tutor = $this->tutor('Moussa Idrissa');
        Internship::factory()->count(3)->forTutor($tutor)->create();

        $candidate = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);
        $this->approvedForm($direction, $tutor, $candidate);
        Objective::factory()->count(3)->create(['user_id' => $candidate->getKey()]);

        $response = $this->actingAs($direction)
            ->from(route('tutors.capacity'))
            ->post(route('interns.activate', $candidate));

        $response->assertSessionHasErrors('tutor_id');
        $this->assertStringContainsString(
            'Moussa Idrissa encadre déjà 3 stagiaires actifs, soit la limite en vigueur. Choisissez un autre tuteur.',
            (string) session('errors')?->first('tutor_id'),
        );

        // Le compte n'est pas activé et aucun stage n'est ouvert.
        $this->assertSame(UserState::Invite, $candidate->refresh()->state);
        $this->assertSame(3, Internship::query()->where('tutor_id', $tutor->getKey())->occupyingTutorSlot()->count());
    }

    private function tutor(string $name): User
    {
        return User::factory()
            ->active()
            ->withRole('tuteur')
            ->for(Person::factory()->state(['full_name' => $name]), 'person')
            ->create();
    }

    private function approvedForm(User $direction, User $tutor, User $candidate): void
    {
        $service = app(InternshipIntakeService::class);
        $form = $service->draft($direction, [
            'candidate_user_id' => (int) $candidate->getKey(),
            'manager_id' => (int) $direction->getKey(),
            'tutor_id' => (int) $tutor->getKey(),
            'real_need' => 'Renfort sur la documentation.',
            'mission' => 'Rédiger les procédures.',
            'duration_weeks' => 12,
            'tools' => 'Poste de travail.',
            'outcomes' => ['Un résultat.', 'Un deuxième.', 'Un troisième.'],
        ]);
        $service->decide($service->submit($form, $direction), $direction, approved: true);
    }
}
