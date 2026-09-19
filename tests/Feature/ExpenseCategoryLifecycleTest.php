<?php

namespace Tests\Feature;

use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Finance\ExpenseCategoryService;
use LogicException;
use Tests\Support\IdentityTestCase;

class ExpenseCategoryLifecycleTest extends IdentityTestCase
{
    public function test_ac_1_3_and_6_create_rename_essential_and_activity_changes_are_audited(): void
    {
        $actor = User::factory()->active()->create();
        $service = app(ExpenseCategoryService::class);

        $category = $service->create('Matériel terrain', false, $actor);
        $service->rename($category, 'Matériel de terrain', $actor);
        $service->setEssential($category, true, $actor);
        $service->deactivate($category, $actor);
        $service->reactivate($category, $actor);

        $category->refresh();
        $this->assertSame('Matériel de terrain', $category->name);
        $this->assertTrue($category->is_essential);
        $this->assertTrue($category->is_active);
        $this->assertEqualsCanonicalizing([
            'expense_category_created',
            'expense_category_renamed',
            'expense_category_essential_changed',
            'expense_category_deactivated',
            'expense_category_reactivated',
        ], AuditLog::query()
            ->where('auditable_type', ExpenseCategory::class)
            ->where('auditable_id', $category->getKey())
            ->pluck('action')
            ->all());
    }

    public function test_ac_1_inactive_category_remains_in_database_and_physical_deletion_is_impossible(): void
    {
        $actor = User::factory()->active()->create();
        $category = ExpenseCategory::factory()->create();
        app(ExpenseCategoryService::class)->deactivate($category, $actor);

        $this->assertDatabaseHas('expense_categories', ['id' => $category->getKey(), 'is_active' => false]);

        $this->expectException(LogicException::class);
        $category->delete();
    }

    public function test_ac_5_new_active_category_is_immediately_available_without_redeployment(): void
    {
        $actor = User::factory()->active()->create();
        $service = app(ExpenseCategoryService::class);
        $category = $service->create('Connexion de secours', true, $actor);

        $this->assertTrue($service->activeForSelection()->contains('id', $category->getKey()));

        $service->deactivate($category, $actor);

        $this->assertFalse($service->activeForSelection()->contains('id', $category->getKey()));
    }
}
