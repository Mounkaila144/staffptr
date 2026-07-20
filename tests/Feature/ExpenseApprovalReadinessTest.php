<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Services\Identity\ExpenseApprovalReadiness;
use App\Services\Identity\RoleAssignmentService;
use Tests\Support\IdentityTestCase;

class ExpenseApprovalReadinessTest extends IdentityTestCase
{
    public function test_ac_9_approval_becomes_available_only_with_exactly_two_authorized_accounts(): void
    {
        $this->seedRbac();
        $readiness = app(ExpenseApprovalReadiness::class);
        $assignments = app(RoleAssignmentService::class);

        $this->assertSame([
            'approval_account_count' => 0,
            'approval_available' => false,
            'message' => ExpenseApprovalReadiness::UNAVAILABLE_MESSAGE,
        ], $readiness->status());

        $firstDirection = User::factory()->active()->create();
        $assignments->assignRole($firstDirection, 'direction', null, 'Test approbation');

        $this->assertSame(1, $readiness->approvalAccountCount());
        $this->assertFalse($readiness->isApprovalAvailable());
        $this->assertSame(ExpenseApprovalReadiness::UNAVAILABLE_MESSAGE, $readiness->status()['message']);

        $secondDirection = User::factory()->active()->create();
        $assignments->assignRole($secondDirection, 'direction', null, 'Test approbation');

        $this->assertSame([
            'approval_account_count' => 2,
            'approval_available' => true,
            'message' => null,
        ], $readiness->status());
    }
}
