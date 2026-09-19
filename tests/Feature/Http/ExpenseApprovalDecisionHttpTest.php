<?php

namespace Tests\Feature\Http;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Notifications\ExpenseRequestedNotification;
use App\Services\Finance\ExpenseService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class ExpenseApprovalDecisionHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    public function test_ac_3_direct_named_notification_link_opens_decision_and_marks_notification_read(): void
    {
        [$firstDirection, , $expense] = $this->fixture();
        /** @var DatabaseNotification $stored */
        $stored = $firstDirection->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ExpenseRequestedNotification::class,
            'data' => [
                'message' => 'Décision attendue.',
                'link' => route('expenses.approvals.show', $expense, false),
            ],
            'read_at' => null,
        ]);

        $this->actingAs($firstDirection)
            ->get(route('expenses.approvals.show', $expense))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/Expenses/Approvals/Index')
                ->where('view', 'decision')
                ->where('focusedExpenseId', $expense->getKey())
                ->where('expenses.0.id', $expense->getKey())
                ->where('expenses.0.can_decide', true)
                ->where('expenses.0.decision_url', route('expenses.approvals.show', $expense, false)));

        $this->assertNotNull($stored->fresh()->read_at);
    }

    public function test_ac_3_direct_decision_approval_returns_to_confirmation_on_same_screen(): void
    {
        [$firstDirection, , $expense] = $this->fixture();

        $this->actingAs($firstDirection)
            ->patch(route('expenses.approve', $expense), ['return_to' => 'decision'])
            ->assertRedirect(route('expenses.approvals.show', $expense))
            ->assertSessionHas('success', 'Votre approbation a été enregistrée.');

        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);
    }

    public function test_ac_3_and_7_direct_view_and_inline_attachment_are_server_protected(): void
    {
        [$firstDirection, , $expense] = $this->fixture();
        $diskName = (string) config('attachments.disk');
        Storage::fake($diskName);
        Storage::disk($diskName)->put('finance/expense/proof.pdf', '%PDF-1.4 test');
        Attachment::factory()->for($expense, 'attachable')->create([
            'disk' => $diskName,
            'path' => 'finance/expense/proof.pdf',
            'original_name' => 'justificatif.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'uploaded_by' => $expense->requester_id,
        ]);

        $this->actingAs($firstDirection)
            ->get(route('expenses.approvals.show', $expense))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('expenses.0.attachment.name', 'justificatif.pdf')
                ->where('expenses.0.attachment.inline_url', route('expenses.approvals.attachment', $expense, false)));
        $this->actingAs($firstDirection)
            ->get(route('expenses.approvals.attachment', $expense))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=justificatif.pdf');

        $finance = User::factory()->active()->withRole('finance')->create();
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($finance)->get(route('expenses.approvals.show', $expense))->assertForbidden();
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($expense->requester)->get(route('expenses.approvals.show', $expense))->assertForbidden();
    }

    public function test_ac_3_initial_notification_targets_only_eligible_approver_with_direct_link(): void
    {
        Notification::fake();
        $requesterDirection = User::factory()->active()->withRole('direction')->create();
        $otherDirection = User::factory()->active()->withRole('direction')->create();
        $category = ExpenseCategory::factory()->create();

        $expense = app(ExpenseService::class)->create(
            requester: $requesterDirection,
            category: $category,
            reason: 'Abonnement de travail',
            requestedAmount: 20_000,
            beneficiary: 'Fournisseur',
            expectedResult: 'Maintenir le service',
            projectOrContractNote: null,
            attachment: null,
            actor: $requesterDirection,
        );

        Notification::assertNotSentTo($requesterDirection, ExpenseRequestedNotification::class);
        Notification::assertSentTo(
            $otherDirection,
            ExpenseRequestedNotification::class,
            static fn (ExpenseRequestedNotification $notification): bool => $notification->link === route('expenses.approvals.show', $expense, false),
        );
    }

    /** @return array{User, User, Expense} */
    private function fixture(): array
    {
        $firstDirection = User::factory()->active()->withRole('direction')->create();
        $secondDirection = User::factory()->active()->withRole('direction')->create();
        $requester = User::factory()->active()->withRole('employe')->create();
        $expense = Expense::factory()
            ->requested()
            ->for($requester, 'requester')
            ->for(ExpenseCategory::factory(), 'category')
            ->create();

        return [$firstDirection, $secondDirection, $expense];
    }
}
