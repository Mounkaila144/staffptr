<?php

namespace Database\Seeders;

use App\Enums\ObjectiveState;
use App\Enums\UserState;
use App\Enums\WorkTaskStatus;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Invoice;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\Setting;
use App\Models\Work\Objective;
use App\Models\Work\Task;
use App\Notifications\DailyReportSubmittedNotification;
use App\Notifications\ExpenseRequestedNotification;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use LogicException;

class AuthenticationE2eSeeder extends Seeder
{
    public const PHONE = '+22790123456';

    public const PASSWORD = 'Temporaire-E2E-2026';

    public const DIRECTION_PHONE = '+22790234567';

    public const DIRECTION_PASSWORD = 'Direction-E2E-2026';

    public const SECOND_DIRECTION_PHONE = '+22790345678';

    public const REQUESTER_PHONE = '+22790456789';

    public const REQUESTER_PASSWORD = 'Employe-E2E-2026';

    public const FINANCE_PHONE = '+22790678901';

    public const FINANCE_PASSWORD = 'Finance-E2E-2026';

    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException("La fixture d'authentification E2E est réservée aux tests.");
        }

        $user = User::query()->where('phone', self::PHONE)->first();

        if (! $user instanceof User) {
            $person = Person::factory()->create(['full_name' => 'Compte E2E Authentification']);
            $user = User::factory()->for($person)->create(['phone' => self::PHONE]);
        }

        $user->forceFill([
            'password' => self::PASSWORD,
            'state' => UserState::Actif,
            'must_change_password' => true,
        ])->saveOrFail();

        $this->call(RolePermissionSeeder::class);
        $this->call(SettingSeeder::class);
        $workingDays = Setting::query()->where('key', 'working_days')->firstOrFail();
        $workingDays->value = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
        $workingDays->saveOrFail();

        $direction = User::query()->where('phone', self::DIRECTION_PHONE)->first();

        if (! $direction instanceof User) {
            $person = Person::factory()->create(['full_name' => 'Direction E2E Historique']);
            $direction = User::factory()->for($person)->create(['phone' => self::DIRECTION_PHONE]);
        }

        $direction->forceFill([
            'password' => self::DIRECTION_PASSWORD,
            'state' => UserState::Actif,
            'must_change_password' => false,
        ])->saveOrFail();
        app(RoleAssignmentService::class)->syncRoles(
            $direction,
            ['direction'],
            null,
            'Fixture E2E',
            'Accès à l’historique de connexion',
        );

        $secondDirection = $this->activeUser(self::SECOND_DIRECTION_PHONE, 'Seconde direction E2E');
        app(RoleAssignmentService::class)->syncRoles(
            $secondDirection,
            ['direction'],
            null,
            'Fixture E2E',
            'Double approbation des dépenses',
        );
        $requester = $this->activeUser(self::REQUESTER_PHONE, 'Membre demandeur E2E');
        app(RoleAssignmentService::class)->syncRoles(
            $requester,
            ['employe'],
            null,
            'Fixture E2E',
            'Demande de dépense pour le parcours critique',
        );
        $requester->forceFill([
            'password' => self::REQUESTER_PASSWORD,
            'manager_id' => $direction->getKey(),
        ])->saveOrFail();
        $finance = $this->activeUser(self::FINANCE_PHONE, 'Finance E2E');
        $finance->forceFill(['password' => self::FINANCE_PASSWORD])->saveOrFail();
        app(RoleAssignmentService::class)->syncRoles(
            $finance,
            ['finance'],
            null,
            'Fixture E2E',
            'Parcours encaissement, parts et réserve',
        );
        $financialClient = Client::factory()->create(['name' => 'Client Finance E2E', 'phone' => '+22790789012']);
        $financialContract = Contract::factory()->create([
            'client_id' => $financialClient->getKey(), 'reference' => 'CTR-E2E-FINANCE', 'title' => 'Contrat finance E2E',
            'expected_total_amount' => 1_000_000, 'forecast_profit_amount' => 1_000_000,
            'contributor_id' => $requester->getKey(), 'has_execution' => true,
        ]);
        $financialContract->executorHistory()->create(['user_id' => $direction->getKey(), 'position' => 1, 'is_active' => true]);
        Invoice::factory()->create([
            'client_id' => $financialClient->getKey(), 'contract_id' => $financialContract->getKey(),
            'number' => 'FAC-E2E-0001', 'total_amount' => 1_000_000,
        ]);
        Account::factory()->create(['label' => 'Caisse principale E2E', 'opening_balance_amount' => 0]);
        FixedCharge::factory()->create(['label' => 'Charge réserve E2E', 'monthly_amount' => 1_000_000, 'is_active' => true]);
        $category = ExpenseCategory::factory()->create(['name' => 'Parcours E2E']);
        $expense = Expense::factory()
            ->requested()
            ->for($requester, 'requester')
            ->for($category, 'category')
            ->create([
                'reason' => 'Connexion internet de secours',
                'requested_amount' => 15_000,
                'expected_result' => "Maintenir l'équipe connectée",
            ]);
        $disk = (string) config('attachments.disk');
        $path = 'finance/expense/e2e-approval-proof.jpg';
        $thumbnailPath = 'finance/expense/thumbnails/e2e-approval-proof.jpg';
        $image = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', true);

        if (! is_string($image)) {
            throw new LogicException('Le justificatif E2E ne peut pas être créé.');
        }

        Storage::disk($disk)->put($path, $image);
        Storage::disk($disk)->put($thumbnailPath, $image);
        Attachment::factory()->for($expense, 'attachable')->create([
            'disk' => $disk,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'original_name' => 'justificatif-e2e.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => strlen($image),
            'uploaded_by' => $requester->getKey(),
        ]);
        Notification::sendNow($direction, new ExpenseRequestedNotification($expense), ['database']);
        Objective::factory()->for($direction, 'owner')->create([
            'created_by' => $direction->getKey(),
            'title' => 'Finaliser le socle du travail',
            'state' => ObjectiveState::EnCours,
            'progress' => 60,
            'due_date' => now('Africa/Niamey')->endOfMonth()->toDateString(),
        ]);
        Task::factory()->for($direction, 'assignee')->create([
            'created_by' => $direction->getKey(),
            'title' => 'Relire les priorités du jour',
            'status' => WorkTaskStatus::AFaire,
            'due_date' => now('Africa/Niamey')->toDateString(),
        ]);
        Task::factory()->for($requester, 'assignee')->create([
            'created_by' => $direction->getKey(),
            'title' => 'Préparer le rapport E2E',
            'status' => WorkTaskStatus::AFaire,
            'due_date' => now('Africa/Niamey')->toDateString(),
        ]);

        $reviewee = $this->activeUser('+22790567890', 'Membre à valider E2E');
        $reviewee->forceFill(['manager_id' => $direction->getKey()])->saveOrFail();
        app(RoleAssignmentService::class)->syncRoles(
            $reviewee,
            ['employe'],
            null,
            'Fixture E2E',
            'Rapport en attente de validation',
        );
        $dailyReport = DailyReport::factory()->submitted()->for($reviewee, 'author')->create();
        DailyReportVersion::factory()->for($dailyReport, 'report')->for($reviewee, 'author')->create([
            'planned_task' => 'Préparer la synthèse quotidienne',
            'achieved_result' => 'La synthèse quotidienne est prête.',
            'evidence_link' => 'https://example.test/preuve-e2e',
            'next_action' => 'Partager la synthèse.',
        ]);
        Notification::sendNow(
            $direction,
            DailyReportSubmittedNotification::forDatabase($dailyReport, $direction),
            ['database'],
        );
    }

    private function activeUser(string $phone, string $fullName): User
    {
        $person = Person::factory()->create(['full_name' => $fullName]);

        return User::factory()->active()->for($person)->create([
            'phone' => $phone,
            'must_change_password' => false,
        ]);
    }
}
