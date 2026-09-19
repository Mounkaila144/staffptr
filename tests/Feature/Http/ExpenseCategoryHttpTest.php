<?php

namespace Tests\Feature\Http;

use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class ExpenseCategoryHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_1_and_3_direction_can_create_update_and_deactivate_a_category(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)->post(route('expense-categories.store'), [
            'name' => 'Équipement informatique',
            'is_essential' => false,
        ])->assertRedirect(route('expense-categories.index'));
        $category = ExpenseCategory::query()->where('name', 'Équipement informatique')->sole();

        $this->actingAs($direction)->patch(route('expense-categories.update', $category), [
            'name' => 'Infrastructure informatique',
            'is_essential' => true,
        ])->assertRedirect(route('expense-categories.index'));
        $this->actingAs($direction)->patch(route('expense-categories.deactivate', $category))->assertRedirect(route('expense-categories.index'));

        $category->refresh();
        $this->assertSame('Infrastructure informatique', $category->name);
        $this->assertTrue($category->is_essential);
        $this->assertFalse($category->is_active);
    }

    public function test_ac_5_index_exposes_a_new_category_immediately_in_active_selection(): void
    {
        $direction = $this->userWithRole('direction');
        $category = ExpenseCategory::factory()->create(['name' => 'Nouvelle nature dynamique']);

        $this->actingAs($direction)->get(route('expense-categories.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Finance/ExpenseCategories/Index')
                ->where('activeCategories.0.id', $category->getKey())
                ->where('activeCategories.0.name', 'Nouvelle nature dynamique')
                ->has('categories', 1),
        );
    }

    public function test_ac_1_validation_rejects_duplicate_or_invalid_categories(): void
    {
        $direction = $this->userWithRole('direction');
        ExpenseCategory::factory()->create(['name' => 'Transport']);

        $this->actingAs($direction)->post(route('expense-categories.store'), [
            'name' => 'Transport',
            'is_essential' => 'invalide',
        ])->assertSessionHasErrors(['name', 'is_essential']);
    }

    public function test_ac_6_direction_and_super_admin_manage_while_other_roles_are_forbidden(): void
    {
        $category = ExpenseCategory::factory()->create();

        foreach (['direction', 'super_admin'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('expense-categories.index'))->assertOk();
            $this->resetAuthentication();
        }

        foreach (['finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('expense-categories.index'))->assertForbidden();
            $this->actingAs($user)->post(route('expense-categories.store'), ['name' => 'Interdit', 'is_essential' => false])->assertForbidden();
            $this->actingAs($user)->patch(route('expense-categories.update', $category), ['name' => 'Interdit', 'is_essential' => false])->assertForbidden();
            $this->actingAs($user)->patch(route('expense-categories.deactivate', $category))->assertForbidden();
            $this->actingAs($user)->patch(route('expense-categories.reactivate', $category))->assertForbidden();
            $this->resetAuthentication();
        }
    }

    public function test_ac_1_and_3_page_has_explicit_labels_empty_state_and_mobile_contract(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Finance/ExpenseCategories/Index.vue'));

        $this->assertStringContainsString('Aucune catégorie de dépense.', $source);
        $this->assertStringContainsString('Dépense essentielle', $source);
        $this->assertStringContainsString('Dépense non essentielle', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP story 4.3');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
