<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Services\Identity\IdentityVisibility;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\Support\IdentityTestCase;

class IdentityAuthorizationScopeTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_5_person_and_user_policies_enforce_permission_and_object_scope(): void
    {
        $reader = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $direction = User::factory()->active()->create();
        $readOnlyRole = Role::create(['name' => 'lecture_identite', 'guard_name' => 'web']);
        $readOnlyRole->givePermissionTo('compte.consulter');
        $service = app(RoleAssignmentService::class);

        $service->assignRole($reader, $readOnlyRole->name, null, 'Test RBAC');
        $service->assignRole($direction, 'direction', null, 'Test RBAC');

        $this->assertTrue(Gate::forUser($reader)->allows('view', $reader->person));
        $this->assertTrue(Gate::forUser($reader)->allows('view', $reader));
        $this->assertTrue(Gate::forUser($reader)->denies('view', $other->person));
        $this->assertTrue(Gate::forUser($reader)->denies('view', $other));
        $this->assertTrue(Gate::forUser($reader)->denies('update', $reader->person));
        $this->assertTrue(Gate::forUser($reader)->denies('delete', $reader));

        $this->assertTrue(Gate::forUser($direction)->allows('view', $other->person));
        $this->assertTrue(Gate::forUser($direction)->allows('update', $other));
        $this->assertTrue(Gate::forUser($direction)->denies('delete', $other));
    }

    public function test_ac_5_index_and_export_reuse_the_same_visibility_scope(): void
    {
        $viewer = User::factory()->active()->create();
        $historicalAccount = User::factory()->for($viewer->person)->archived()->create();
        $other = User::factory()->active()->create();
        $visibility = app(IdentityVisibility::class);

        app(RoleAssignmentService::class)->grantPermission(
            $viewer,
            'compte.consulter',
            null,
            'Test RBAC',
        );

        $personIndex = $visibility->peopleIndex($viewer)->pluck('id')->all();
        $personExport = $visibility->peopleExport($viewer)->pluck('id')->all();
        $userIndex = $visibility->usersIndex($viewer)->pluck('id')->all();
        $userExport = $visibility->usersExport($viewer)->pluck('id')->all();

        $this->assertSame($personIndex, $personExport);
        $this->assertSame($userIndex, $userExport);
        $this->assertSame([$viewer->person_id], $personIndex);
        $this->assertEqualsCanonicalizing([$viewer->getKey(), $historicalAccount->getKey()], $userIndex);
        $this->assertNotContains($other->getKey(), $userIndex);
    }

    public function test_ac_5_direction_scope_sees_all_identity_records(): void
    {
        $direction = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $visibility = app(IdentityVisibility::class);

        app(RoleAssignmentService::class)->assignRole($direction, 'direction', null, 'Test RBAC');

        $this->assertContains($other->person_id, $visibility->peopleIndex($direction)->pluck('id')->all());
        $this->assertContains($other->getKey(), $visibility->usersIndex($direction)->pluck('id')->all());
    }
}
