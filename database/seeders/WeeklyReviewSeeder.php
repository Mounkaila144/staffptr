<?php

namespace Database\Seeders;

use App\Enums\ReviewObjectiveStatus;
use App\Enums\WeeklyReviewState;
use App\Models\Accountability\WeeklyReview;
use App\Models\Accountability\WeeklyReviewObjective;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class WeeklyReviewSeeder extends Seeder
{
    /**
     * Revue de référence, ouverte en brouillon sur la semaine courante et calée
     * sur le vendredi, périodicité par défaut de l'Epic 7.
     */
    public function run(): void
    {
        $objective = Objective::query()->with('owner.manager')->orderBy('id')->first();

        if (! $objective instanceof Objective || ! $objective->owner instanceof User) {
            return;
        }

        $subject = $objective->owner;
        $reviewer = $subject->manager instanceof User ? $subject->manager : $subject;
        $weekStart = CarbonImmutable::parse('2026-08-10', 'Africa/Niamey')->startOfWeek();

        // `whereDate` compare la date civile quel que soit le moteur : SQLite conserve
        // l'horodatage sérialisé par le cast là où MySQL tronque la colonne `date`.
        $review = WeeklyReview::query()
            ->where('subject_user_id', $subject->getKey())
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first() ?? new WeeklyReview;

        $review->fill([
            'subject_user_id' => $subject->getKey(),
            'week_start_date' => $weekStart->toDateString(),
            'reviewer_id' => $reviewer->getKey(),
            'scheduled_on' => $weekStart->addDays(4)->toDateString(),
            'state' => WeeklyReviewState::Brouillon,
        ])->save();

        WeeklyReviewObjective::query()->updateOrCreate(
            [
                'weekly_review_id' => $review->getKey(),
                'objective_id' => $objective->getKey(),
            ],
            [
                'result' => 'Deux engagements sur trois sont documentés.',
                'evidence' => 'Document de synthèse partagé le vendredi.',
                'status' => ReviewObjectiveStatus::PartiellementAtteint,
                'gap_cause' => "Le troisième engagement dépendait d'une information encore attendue.",
                'next_action' => 'Documenter le dernier engagement en début de semaine suivante.',
            ],
        );
    }
}
