<?php

namespace Tests\Feature\Http;

use App\Models\Accountability\Internship;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Identity\User;
use Database\Seeders\SettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/** Créneaux de suivi : le stagiaire voit ceux de son tuteur, le tuteur les siens. */
class TutorSupportSlotHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /** L'écran disait « aucun créneau » au stagiaire alors que son tuteur en avait fixé un. */
    public function test_the_intern_sees_the_slots_of_their_tutor(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $tutor->person->forceFill(['full_name' => 'Hamidou Tuteur'])->save();
        $intern = User::factory()->active()->withRole('stagiaire')->create();
        Internship::factory()->forTutor($tutor)->create(['user_id' => $intern->getKey()]);
        TutorSupportSlot::factory()->create(['tutor_id' => $tutor->getKey(), 'weekday' => 4, 'start_time' => '16:30:00']);

        $this->actingAs($intern)->get(route('support-slots.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('canManage', false)
                ->where('tutorName', 'Hamidou Tuteur')
                ->has('slots', 1)
                ->where('slots.0.weekday_label', 'jeudi')
                ->where('nextSlot', fn (?string $next): bool => is_string($next) && str_ends_with($next, '16:30')));

        $this->flushSession();
        $this->actingAs($tutor)->get(route('support-slots.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('canManage', true)
                ->where('tutorName', null)
                ->has('slots', 1));
    }
}
