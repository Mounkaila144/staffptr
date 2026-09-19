<?php

namespace Tests\Feature\Http;

use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Services\Finance\ExpenseApprovalQueueService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeApprovalQueueHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_ac_1_and_2_direction_home_exposes_only_personal_pending_queue_oldest_first(): void
    {
        CarbonImmutable::setTestNow('2026-08-10 08:00:00 Africa/Niamey');
        $direction = User::factory()->active()->withRole('direction')->create();
        User::factory()->active()->withRole('direction')->create();
        $requester = User::factory()->active()->withRole('employe')->create();
        $category = ExpenseCategory::factory()->create();
        $oldest = $this->expense($requester, $category, 'Ancienne demande', '2026-08-08 07:00:00');
        $newest = $this->expense($requester, $category, 'Nouvelle demande', '2026-08-09 12:00:00');
        $this->expense($direction, $category, 'Ma propre demande', '2026-08-07 12:00:00');
        $handled = $this->expense($requester, $category, 'Déjà traitée', '2026-08-06 12:00:00');
        ExpenseApproval::factory()->approved()->create([
            'expense_id' => $handled->getKey(),
            'approver_id' => $direction->getKey(),
        ]);

        $this->actingAs($direction)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Identity/Home')
                ->where('approvalQueue.count', 2)
                ->where('approvalQueue.oldest_age_days', 2)
                ->where('approvalQueue.oldest_age_label', '2 jours')
                ->where('approvalQueue.items.0.id', $oldest->getKey())
                ->where('approvalQueue.items.1.id', $newest->getKey())
                ->where('approvalQueue.empty_message', ExpenseApprovalQueueService::EMPTY_MESSAGE)
                ->where('approvalQueue.handled_url', '/depenses/approbations?vue=traitees'));
    }

    public function test_ac_1_block_is_not_exposed_to_a_non_approver(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();

        $this->actingAs($employee)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Identity/Home')
                ->where('approvalQueue', null));
    }

    public function test_ac_6_empty_direction_block_is_normal_and_carries_exact_message_and_handled_link(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        User::factory()->active()->withRole('direction')->create();

        $this->actingAs($direction)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('approvalQueue.count', 0)
                ->where('approvalQueue.items', [])
                ->where('approvalQueue.empty_message', ExpenseApprovalQueueService::EMPTY_MESSAGE)
                ->where('approvalQueue.handled_url', '/depenses/approbations?vue=traitees'));
    }

    private function expense(User $requester, ExpenseCategory $category, string $reason, string $createdAt): Expense
    {
        return Expense::factory()
            ->requested()
            ->for($requester, 'requester')
            ->for($category, 'category')
            ->create([
                'reason' => $reason,
                'created_at' => CarbonImmutable::parse($createdAt, 'Africa/Niamey')->utc(),
                'updated_at' => CarbonImmutable::parse($createdAt, 'Africa/Niamey')->utc(),
            ]);
    }
}
