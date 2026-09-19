<?php

namespace Tests\Feature;

use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipChecklistItem;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\InternshipIntakeOutcome;
use App\Models\Accountability\InternshipPlan;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Accountability\WeeklyReview;
use App\Models\Accountability\WeeklyReviewObjective;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Platform\SettingsService;
use Database\Seeders\InternshipSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WeeklyReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les seeders de l'Epic 7 sont rejouables sans doublon (SOC-04).
 */
class EpicSevenSeederIdempotenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_review_seeder_is_idempotent(): void
    {
        $manager = User::factory()->create();
        $owner = User::factory()->create(['manager_id' => $manager->getKey()]);
        Objective::factory()->create(['user_id' => $owner->getKey()]);

        $this->seed(WeeklyReviewSeeder::class);
        $this->seed(WeeklyReviewSeeder::class);

        $this->assertSame(1, WeeklyReview::query()->count());
        $this->assertSame(1, WeeklyReviewObjective::query()->count());

        $review = WeeklyReview::query()->firstOrFail();
        $this->assertSame($owner->getKey(), $review->subject_user_id);
        $this->assertSame($manager->getKey(), $review->reviewer_id);
        // Périodicité hebdomadaire par défaut le vendredi (AC 1).
        $this->assertSame('2026-08-14', $review->scheduled_on->toDateString());
    }

    public function test_internship_seeder_is_idempotent_and_produces_a_complete_dossier(): void
    {
        User::factory()->count(2)->create(['state' => 'actif']);

        $this->seed(InternshipSeeder::class);
        $this->seed(InternshipSeeder::class);

        $this->assertSame(1, InternshipIntakeForm::query()->count());
        $this->assertSame(1, Internship::query()->count());
        $this->assertSame(1, InternshipPlan::query()->count());
        $this->assertSame(1, TutorSupportSlot::query()->count());
        // Trois résultats attendus (AC 14) et six éléments d'intégration (AC 17).
        $this->assertSame(3, InternshipIntakeOutcome::query()->count());
        $this->assertSame(6, InternshipChecklistItem::query()->count());
    }

    public function test_tutor_limit_setting_is_seeded_at_three_and_stays_parametrable(): void
    {
        $this->seed(SettingSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->assertSame(3, app(SettingsService::class)->internLimitPerTutor());
        $this->assertDatabaseCount('settings', count(SettingsService::keys()));
    }
}
