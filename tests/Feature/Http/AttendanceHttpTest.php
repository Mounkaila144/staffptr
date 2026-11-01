<?php

namespace Tests\Feature\Http;

use App\Models\Accountability\AttendanceRecord;
use App\Models\Accountability\Internship;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Platform\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/** Story 13.1 — arrivées et départs des stagiaires, enregistrés au poste de la direction. */
class AttendanceHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    private User $direction;

    private User $tutor;

    private User $intern;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->direction = User::factory()->active()->withRole('direction')->create();
        $this->tutor = User::factory()->active()->withRole('tuteur')->create();
        $this->intern = User::factory()->active()->withRole('stagiaire')->create();
        Internship::factory()->forTutor($this->tutor)->create(['user_id' => $this->intern->getKey()]);
    }

    #[Test]
    public function an_arrival_within_the_tolerance_is_on_time_and_its_time_comes_from_the_server(): void
    {
        // 8 h 07 à Niamey (7 h 07 UTC) : sept minutes, sous la tolérance de dix.
        $this->travelTo(CarbonImmutable::parse('2026-10-26 07:07:00', 'UTC'));

        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/arrivee", ['arrived_at' => '2026-10-26 05:00:00'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Arrivée enregistrée à 08h07.');

        $record = AttendanceRecord::query()->sole();
        // L'heure envoyée par le client est ignorée : seule compte l'horloge du serveur.
        $this->assertSame('2026-10-26 07:07:00', $record->arrived_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(0, $record->late_minutes);
        $this->assertSame((int) $this->direction->getKey(), $record->arrival_recorded_by);
        $this->assertTrue(AuditLog::query()->where('action', 'attendance_arrival_recorded')->exists());
    }

    #[Test]
    public function a_late_arrival_is_measured_from_the_expected_time_once_past_the_tolerance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 07:25:00', 'UTC'));

        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/arrivee")
            ->assertSessionHas('success', 'Arrivée enregistrée à 08h25 — retard de 25 min.');

        $this->assertSame(25, AttendanceRecord::query()->sole()->late_minutes);
    }

    #[Test]
    public function a_second_arrival_the_same_day_and_a_departure_without_arrival_are_refused(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 07:00:00', 'UTC'));
        $other = User::factory()->active()->withRole('stagiaire')->create();
        Internship::factory()->forTutor($this->tutor)->create(['user_id' => $other->getKey()]);

        $this->actingAs($this->direction)->post("/arrivees-departs/{$other->getKey()}/depart")->assertSessionHasErrors('attendance');
        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/arrivee")->assertSessionHasNoErrors();
        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/arrivee")->assertSessionHasErrors('attendance');

        $this->assertSame(1, AttendanceRecord::query()->count());
    }

    #[Test]
    public function the_departure_completes_the_day_once_and_only_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 07:00:00', 'UTC'));
        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/arrivee");
        $this->travelTo(CarbonImmutable::parse('2026-10-26 16:30:00', 'UTC'));

        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/depart")
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Départ enregistré à 17h30.');
        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/depart")->assertSessionHasErrors('attendance');

        $record = AttendanceRecord::query()->sole();
        $this->assertSame('2026-10-26 16:30:00', $record->left_at?->utc()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function neither_the_intern_nor_the_tutor_can_record_even_by_direct_address(): void
    {
        foreach ([$this->intern, $this->tutor] as $user) {
            $this->actingAs($user)->post("/arrivees-departs/{$this->intern->getKey()}/arrivee")->assertForbidden();
            $this->actingAs($user)->post("/arrivees-departs/{$this->intern->getKey()}/depart")->assertForbidden();
            $this->resetAuthentication();
        }

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    #[Test]
    public function an_intern_without_an_active_internship_cannot_be_recorded(): void
    {
        $former = User::factory()->active()->withRole('stagiaire')->create();
        Internship::factory()->forTutor($this->tutor)->ended()->create(['user_id' => $former->getKey()]);

        $this->actingAs($this->direction)->post("/arrivees-departs/{$former->getKey()}/arrivee")->assertSessionHasErrors('attendance');
        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    #[Test]
    public function each_viewer_sees_only_the_interns_of_their_scope(): void
    {
        $outsider = User::factory()->active()->withRole('stagiaire')->create();
        Internship::factory()->forTutor(User::factory()->active()->withRole('tuteur')->create())->create(['user_id' => $outsider->getKey()]);

        $this->actingAs($this->direction)->get('/arrivees-departs')->assertInertia(fn (Assert $page): Assert => $page
            ->component('Accountability/Attendance/Index')->where('can_record', true)->has('register', 2));
        $this->resetAuthentication();
        $this->actingAs($this->tutor)->get('/arrivees-departs')->assertInertia(fn (Assert $page): Assert => $page
            ->where('can_record', false)->has('register', 1)->where('register.0.intern_id', (int) $this->intern->getKey()));
        $this->resetAuthentication();
        $this->actingAs($this->intern)->get('/arrivees-departs')->assertInertia(fn (Assert $page): Assert => $page
            ->where('can_record', false)->has('register', 1)->where('register.0.intern_id', (int) $this->intern->getKey()));
        $this->resetAuthentication();
        $this->actingAs(User::factory()->active()->withRole('employe')->create())->get('/arrivees-departs')->assertForbidden();
    }

    /** Au-delà d'une heure, le retard se lit en heures et minutes, pas en « 442 min ». */
    #[Test]
    public function a_lateness_over_an_hour_is_written_in_hours_and_minutes(): void
    {
        // 15 h 22 à Niamey : 7 h 22 après 8 h 00.
        $this->travelTo(CarbonImmutable::parse('2026-10-26 14:22:00', 'UTC'));

        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/arrivee")
            ->assertSessionHas('success', 'Arrivée enregistrée à 15h22 — retard de 7 h 22 min.');
        $this->actingAs($this->direction)->get('/arrivees-departs')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('register.0.record.late_minutes', 442)
                ->where('register.0.record.late_label', 'Retard de 7 h 22 min'));
    }

    #[Test]
    public function changing_the_expected_time_never_rewrites_past_lateness(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 07:25:00', 'UTC'));
        $this->actingAs($this->direction)->post("/arrivees-departs/{$this->intern->getKey()}/arrivee");

        app(SettingsService::class)->update(['intern_arrival_time' => '09:00'], CarbonImmutable::now('UTC'), $this->direction);

        $record = AttendanceRecord::query()->sole()->refresh();
        $this->assertSame('08:00', $record->expected_arrival_time);
        $this->assertSame(25, $record->late_minutes);
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
