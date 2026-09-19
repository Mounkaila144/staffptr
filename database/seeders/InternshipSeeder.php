<?php

namespace Database\Seeders;

use App\Enums\InternshipChecklistType;
use App\Enums\InternshipIntakeState;
use App\Enums\InternshipState;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipChecklistItem;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\InternshipIntakeOutcome;
use App\Models\Accountability\InternshipPlan;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class InternshipSeeder extends Seeder
{
    /**
     * Dossier de stage de référence : fiche d'entrée approuvée, trois résultats attendus,
     * stage actif, plan, checklist d'intégration et créneau de suivi du tuteur.
     */
    public function run(): void
    {
        $intern = User::query()->where('state', 'actif')->orderByDesc('id')->first();
        $tutor = User::query()->where('state', 'actif')->orderBy('id')->first();

        if (! $intern instanceof User || ! $tutor instanceof User || $intern->is($tutor)) {
            return;
        }

        $decidedAt = CarbonImmutable::parse('2026-08-03 09:00:00', 'UTC');

        $form = InternshipIntakeForm::query()->updateOrCreate(
            ['candidate_user_id' => $intern->getKey()],
            [
                'manager_id' => $tutor->getKey(),
                'tutor_id' => $tutor->getKey(),
                'real_need' => 'Renfort sur la documentation des procédures internes.',
                'mission' => 'Rédiger et tenir à jour les procédures du module de suivi.',
                'duration_weeks' => 12,
                'tools' => 'Poste de travail, accès à PTR Staff, dossier partagé.',
                'state' => InternshipIntakeState::Approuvee,
                'submitted_at' => $decidedAt->subDay(),
                'decided_by' => $tutor->getKey(),
                'decided_at' => $decidedAt,
            ],
        );

        $outcomes = [
            'Trois procédures rédigées et relues.',
            'Un guide de prise en main livré.',
            'Une présentation de restitution tenue.',
        ];

        foreach ($outcomes as $index => $description) {
            InternshipIntakeOutcome::query()->updateOrCreate(
                [
                    'internship_intake_form_id' => $form->getKey(),
                    'position' => $index + 1,
                ],
                ['description' => $description],
            );
        }

        $internship = Internship::query()->updateOrCreate(
            ['user_id' => $intern->getKey()],
            [
                'internship_intake_form_id' => $form->getKey(),
                'tutor_id' => $tutor->getKey(),
                'state' => InternshipState::Actif,
                'start_date' => '2026-08-10',
                'end_date' => null,
                'ended_at' => null,
            ],
        );

        InternshipPlan::query()->updateOrCreate(
            ['internship_id' => $internship->getKey()],
            [
                'skills_to_learn' => 'Rédaction de procédure, relecture, restitution orale.',
                'objectives' => 'Documenter le module de suivi de bout en bout.',
                'weekly_tasks' => 'Une procédure rédigée et relue par semaine.',
                'expected_evidence' => 'Document versionné et relu par le tuteur.',
            ],
        );

        foreach (InternshipChecklistType::Integration->items() as $index => $label) {
            InternshipChecklistItem::query()->updateOrCreate(
                [
                    'internship_id' => $internship->getKey(),
                    'checklist_type' => InternshipChecklistType::Integration,
                    'position' => $index + 1,
                ],
                ['label' => $label],
            );
        }

        // Créneau de suivi du tuteur : mardi 10 h, heure de Niamey (AC 34).
        TutorSupportSlot::query()->updateOrCreate(
            [
                'tutor_id' => $tutor->getKey(),
                'weekday' => 2,
                'start_time' => '10:00:00',
            ],
            [],
        );
    }
}
