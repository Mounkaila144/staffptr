<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InterfaceFoundationTest extends TestCase
{
    public function test_ac_1_demo_exposes_the_shared_permissions_contract_and_fixture(): void
    {
        $this->get(route('platform.demo'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Demo')
                ->has('auth.permissions', 8)
                ->where('auth.permissions.0', 'role:direction')
                ->where('auth.permissions.1', 'role:finance')
                ->where('auth.permissions.2', 'navigation.home'));
    }

    public function test_ac_1_default_shared_permissions_contract_is_empty(): void
    {
        $middleware = app(HandleInertiaRequests::class);
        $shared = $middleware->share(request());

        $this->assertSame([], $shared['auth']['permissions']);
    }
}
