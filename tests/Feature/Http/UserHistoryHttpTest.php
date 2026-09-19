<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Models\Identity\UserHistory;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class UserHistoryHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_2_history_page_displays_newest_entries_and_empty_state_contract(): void
    {
        $target = $this->userWithRole('employe');
        UserHistory::factory()->create([
            'user_id' => $target->getKey(),
            'field' => 'manager_id',
            'old_value' => 'Aïcha',
            'new_value' => 'Moussa',
            'changed_by' => null,
        ]);

        $this->actingAs($target)
            ->get(route('people.history', $target->person))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Identity/People/History')
                ->where('person.id', $target->person_id)
                ->where('history.data.0.field_label', 'Responsable')
                ->where('history.data.0.old_value', 'Aïcha')
                ->where('history.data.0.new_value', 'Moussa')
                ->where('history.data.0.author', 'Système'));

        $source = (string) file_get_contents(resource_path('js/Pages/Identity/People/History.vue'));
        $this->assertStringContainsString('Aucun changement enregistré pour cette fiche.', $source);
        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    public function test_ac_5_history_is_visible_to_self_manager_and_direction_but_not_peer_or_super_admin(): void
    {
        $manager = $this->userWithRole('tuteur');
        $target = $this->userWithRole('employe', ['manager_id' => $manager->getKey()]);
        $peer = $this->userWithRole('employe');
        $direction = $this->userWithRole('direction');
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($target)->get(route('people.history', $target->person))->assertOk();
        $this->resetAuthentication();
        $this->actingAs($manager)->get(route('people.history', $target->person))->assertOk();
        $this->resetAuthentication();
        $this->actingAs($direction)->get(route('people.history', $target->person))->assertOk();
        $this->resetAuthentication();
        $this->actingAs($peer)->get(route('people.history', $target->person))->assertForbidden();
        $this->resetAuthentication();
        $this->actingAs($superAdmin)->get(route('people.history', $target->person))->assertForbidden();
    }

    /** @param array<string, mixed> $attributes */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->active()->create($attributes);
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP story 3.3');

        return $user->fresh('person');
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
