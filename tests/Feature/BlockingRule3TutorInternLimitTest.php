<?php

namespace Tests\Feature;

use App\Enums\InternshipState;
use App\Models\Accountability\Internship;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\Setting;
use App\Services\Accountability\InternshipService;
use App\Services\Accountability\TutorCapacityService;
use App\Services\Platform\SettingsService;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Règle métier bloquante n° 3 de l'Epic 7 : maximum 3 stagiaires actifs par tuteur, bloquant
 * (RM-06, CA-04, FR85). AC 20 à 26 et AC 40.
 *
 * L'absence de ce test bloque la porte de qualité de l'epic.
 */
class BlockingRule3TutorInternLimitTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /**
     * AC 21 : avec la valeur initiale de 3, le troisième stagiaire est accepté et le quatrième
     * refusé.
     */
    public function test_ac_21_the_third_intern_is_accepted_and_the_fourth_is_refused(): void
    {
        $tutor = $this->tutor('Moussa Idrissa');
        $this->assertSame(3, app(SettingsService::class)->internLimitPerTutor());

        $this->giveActiveInterns($tutor, 2);
        $this->assertSame(2, $this->capacity()->currentLoad($tutor));
        $this->assertFalse($this->capacity()->hasReachedLimit($tutor));

        // Le troisième passe.
        $this->capacity()->assertCanAcceptAnotherIntern($tutor);
        $this->giveActiveInterns($tutor, 1);

        $this->assertSame(3, $this->capacity()->currentLoad($tutor));
        $this->assertTrue($this->capacity()->hasReachedLimit($tutor));
        $this->assertSame(0, $this->capacity()->remainingSlots($tutor));

        // Le quatrième est refusé.
        $this->expectException(ValidationException::class);
        $this->capacity()->assertCanAcceptAnotherIntern($tutor->refresh());
    }

    /**
     * AC 20 : le refus vient du serveur et le message nomme le tuteur et sa charge actuelle.
     */
    public function test_ac_20_the_refusal_names_the_tutor_and_their_current_load(): void
    {
        $tutor = $this->tutor('Moussa Idrissa');
        $this->giveActiveInterns($tutor, 3);

        try {
            $this->capacity()->assertCanAcceptAnotherIntern($tutor);
            $this->fail('La limite atteinte doit refuser une affectation supplémentaire.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Moussa Idrissa encadre déjà 3 stagiaires actifs, soit la limite en vigueur. Choisissez un autre tuteur.',
                $exception->errors()['tutor_id'][0],
            );
        }
    }

    /**
     * AC 22 : la limite est relue du paramétrage à chaque contrôle. Portée à 2, elle refuse le
     * troisième — sans redéploiement ni redémarrage.
     */
    public function test_ac_22_the_limit_is_read_from_settings_at_every_check(): void
    {
        $tutor = $this->tutor('Aïcha Souley');
        $this->giveActiveInterns($tutor, 2);

        // Avec la limite initiale de 3, deux stagiaires laissent une place.
        $this->assertSame(3, $this->capacity()->limit());
        $this->assertFalse($this->capacity()->hasReachedLimit($tutor));
        $this->capacity()->assertCanAcceptAnotherIntern($tutor);

        $this->setInternLimit(2);

        // Même processus, même objet de service : la nouvelle valeur s'applique immédiatement.
        $this->assertSame(2, $this->capacity()->limit());
        $this->assertTrue($this->capacity()->hasReachedLimit($tutor));

        try {
            $this->capacity()->assertCanAcceptAnotherIntern($tutor);
            $this->fail('Le troisième stagiaire doit être refusé après passage de la limite à 2.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('encadre déjà 2 stagiaires actifs', $exception->errors()['tutor_id'][0]);
        }
    }

    /**
     * AC 23 : seuls les stages actifs comptent. Un stage terminé ou archivé libère une place.
     */
    public function test_ac_23_only_active_internships_occupy_a_slot(): void
    {
        $tutor = $this->tutor('Hamidou Garba');
        $interns = $this->giveActiveInterns($tutor, 3);

        $this->assertTrue($this->capacity()->hasReachedLimit($tutor));

        // Un départ libère immédiatement une place.
        app(InternshipService::class)->end($interns[0], $this->direction());

        $this->assertSame(2, $this->capacity()->currentLoad($tutor));
        $this->assertFalse($this->capacity()->hasReachedLimit($tutor));
        $this->capacity()->assertCanAcceptAnotherIntern($tutor);

        // Un stage archivé ne réoccupe pas la place.
        $interns[1]->forceFill(['state' => InternshipState::Archive])->save();
        $this->assertSame(1, $this->capacity()->currentLoad($tutor));
    }

    /**
     * AC 24 : un employé porteur du rôle `tuteur` est soumis exactement à la même limite qu'un
     * associé. La règle ne consulte aucun rôle.
     */
    public function test_ac_24_an_employee_tutor_faces_exactly_the_same_limit_as_an_associate(): void
    {
        $employeeTutor = $this->tutor('Fati Abdou', role: 'tuteur');
        $associate = $this->tutor('Ibrahim Yacouba', role: 'direction');

        foreach ([$employeeTutor, $associate] as $tutor) {
            $this->giveActiveInterns($tutor, 3);
            $this->assertTrue($this->capacity()->hasReachedLimit($tutor));
            $this->assertSame(3, $this->capacity()->limit());

            try {
                $this->capacity()->assertCanAcceptAnotherIntern($tutor);
                $this->fail("La limite doit s'appliquer à {$tutor->person->full_name}.");
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('soit la limite en vigueur', $exception->errors()['tutor_id'][0]);
            }
        }
    }

    /**
     * AC 25 : l'écran de gestion donne la charge de chaque tuteur et signale par un libellé,
     * et pas seulement par une couleur, celui qui a atteint la limite.
     */
    public function test_ac_25_the_management_summary_labels_the_tutor_at_the_limit(): void
    {
        $full = $this->tutor('Moussa Idrissa');
        $available = $this->tutor('Aïcha Souley');
        $this->giveActiveInterns($full, 3);
        $this->giveActiveInterns($available, 1);

        $summary = collect($this->capacity()->loadSummary())->keyBy('name');

        $this->assertSame(3, $summary['Moussa Idrissa']['active_interns']);
        $this->assertTrue($summary['Moussa Idrissa']['at_limit']);
        $this->assertSame('Limite atteinte', $summary['Moussa Idrissa']['limit_label']);
        $this->assertSame('3 stagiaires sur 3', $summary['Moussa Idrissa']['load_label']);

        $this->assertSame(1, $summary['Aïcha Souley']['active_interns']);
        $this->assertFalse($summary['Aïcha Souley']['at_limit']);
        $this->assertNull($summary['Aïcha Souley']['limit_label']);
        $this->assertSame('1 stagiaire sur 3', $summary['Aïcha Souley']['load_label']);
        $this->assertSame(2, $summary['Aïcha Souley']['remaining']);
    }

    /**
     * AC 20 et 26 : la réaffectation vers un tuteur saturé est refusée, et le stage garde son
     * tuteur d'origine.
     */
    public function test_ac_20_reassigning_to_a_saturated_tutor_is_refused(): void
    {
        $saturated = $this->tutor('Moussa Idrissa');
        $available = $this->tutor('Aïcha Souley');
        $this->giveActiveInterns($saturated, 3);
        $moving = $this->giveActiveInterns($available, 1)[0];

        try {
            app(InternshipService::class)->assignTutor($moving, $saturated, $this->direction());
            $this->fail('La réaffectation vers un tuteur saturé doit être refusée.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Moussa Idrissa encadre déjà 3', $exception->errors()['tutor_id'][0]);
        }

        $this->assertSame($available->getKey(), $moving->refresh()->tutor_id);
        $this->assertSame(3, $this->capacity()->currentLoad($saturated));
    }

    public function test_ac_23_a_freed_slot_allows_a_new_assignment(): void
    {
        $tutor = $this->tutor('Hamidou Garba');
        $interns = $this->giveActiveInterns($tutor, 3);
        $elsewhere = $this->tutor('Aïcha Souley');
        $moving = $this->giveActiveInterns($elsewhere, 1)[0];

        app(InternshipService::class)->end($interns[0], $this->direction());
        $reassigned = app(InternshipService::class)->assignTutor($moving, $tutor, $this->direction());

        $this->assertSame($tutor->getKey(), $reassigned->tutor_id);
        $this->assertSame(3, $this->capacity()->currentLoad($tutor));
    }

    /**
     * AC 40 — porte du Jalon 3. Les quatre propriétés de la règle sont reprises ensemble :
     * bloquante, lue du paramétrage, libérée par un départ, identique pour les associés et les
     * employés tuteurs.
     */
    public function test_ac_40_the_epic_gate_holds_on_all_four_properties(): void
    {
        $associate = $this->tutor('Ibrahim Yacouba', role: 'direction');
        $employeeTutor = $this->tutor('Fati Abdou', role: 'tuteur');

        foreach ([$associate, $employeeTutor] as $tutor) {
            $interns = $this->giveActiveInterns($tutor, 3);

            // 1. Bloquante, et 4. identique quel que soit le rôle.
            $this->assertTrue($this->capacity()->hasReachedLimit($tutor));
            try {
                $this->capacity()->assertCanAcceptAnotherIntern($tutor);
                $this->fail('La limite doit bloquer.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('soit la limite en vigueur', $exception->errors()['tutor_id'][0]);
            }

            // 3. Un départ libère une place.
            app(InternshipService::class)->end($interns[0], $this->direction());
            $this->assertFalse($this->capacity()->hasReachedLimit($tutor));
            $this->assertSame(2, $this->capacity()->currentLoad($tutor));
        }

        // 2. Lue du paramétrage : la limite portée à 2 rebloque immédiatement les deux tuteurs.
        $this->setInternLimit(2);

        foreach ([$associate, $employeeTutor] as $tutor) {
            $this->assertSame(2, $this->capacity()->limit());
            $this->assertTrue($this->capacity()->hasReachedLimit($tutor));
        }
    }

    private function capacity(): TutorCapacityService
    {
        return app(TutorCapacityService::class);
    }

    private function setInternLimit(int $value): void
    {
        Setting::query()
            ->where('key', 'intern_limit_per_tutor')
            ->update(['value' => json_encode($value, JSON_THROW_ON_ERROR)]);
        Cache::forget(SettingsService::CACHE_KEY);
    }

    private function tutor(string $name, string $role = 'tuteur'): User
    {
        return User::factory()
            ->active()
            ->withRole($role)
            ->for(Person::factory()->state(['full_name' => $name]), 'person')
            ->create();
    }

    private function direction(): User
    {
        return User::factory()->active()->withRole('direction')->create();
    }

    /** @return list<Internship> */
    private function giveActiveInterns(User $tutor, int $count): array
    {
        $created = [];

        for ($index = 0; $index < $count; $index++) {
            $created[] = Internship::factory()->forTutor($tutor)->create();
        }

        return $created;
    }
}
