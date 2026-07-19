<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IdentityDeletionSurfaceTest extends TestCase
{
    public function test_ac_5_no_delete_route_targets_people_or_users(): void
    {
        $identityDeleteRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('DELETE', $route->methods(), true))
            ->filter(fn ($route): bool => preg_match('/(^|\/)(people|users)(\/|$)/', $route->uri()) === 1);

        $this->assertCount(0, $identityDeleteRoutes);
        $this->delete('/people/1')->assertNotFound();
        $this->delete('/users/1')->assertNotFound();
    }
}
