<?php

namespace Tests\Feature\Http;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_ac_4_forbidden_response_is_a_full_inertia_page_and_never_a_redirect(): void
    {
        $response = $this->get(route('testing.errors', ['status' => 403]));

        $response->assertForbidden()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Platform/403'));
        $this->assertFalse($response->isRedirection());
    }

    public function test_ac_4_missing_page_is_rendered_by_the_dedicated_inertia_component(): void
    {
        $this->get('/page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Platform/404'));
    }

    public function test_ac_4_server_failure_has_a_short_support_reference_without_details(): void
    {
        $this->get(route('testing.errors', ['status' => 500]))
            ->assertServerError()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Platform/500')
                ->where('reference', fn (mixed $reference): bool => is_string($reference)
                    && preg_match('/^[a-f0-9]{8}$/', $reference) === 1));
    }

    public function test_ac_5_expired_session_is_a_normal_inertia_page_that_does_not_clear_a_draft(): void
    {
        $this->get(route('testing.errors', ['status' => 419]))
            ->assertStatus(419)
            ->assertInertia(fn (Assert $page): Assert => $page->component('Platform/419'));

        $page = $this->readPage('419');

        $this->assertStringContainsString('reconnecter', $page);
        $this->assertStringContainsString('brouillon', $page);
        $this->assertStringNotContainsString('localStorage.clear', $page);
        $this->assertStringNotContainsString('removeItem', $page);
    }

    public function test_ac_4_error_copy_is_french_actionable_and_contains_no_technical_error_code(): void
    {
        foreach (['403', '404', '419', '500'] as $status) {
            $page = $this->readPage($status);

            $this->assertStringNotContainsString(">{$status}<", $page);

            foreach (['stack trace', 'exception', 'SQLSTATE', 'Internal Server Error'] as $technicalTerm) {
                $this->assertStringNotContainsString($technicalTerm, $page);
            }
        }

        $this->assertStringContainsString("Vous n'avez pas accès à cette page.", $this->readPage('403'));
        $this->assertStringContainsString("L'application rencontre un problème", $this->readPage('500'));
    }

    public function test_ac_3_mutating_web_routes_reject_requests_without_a_csrf_token(): void
    {
        $this->app->instance('env', 'production');

        try {
            $this->post(route('testing.csrf'))
                ->assertStatus(419)
                ->assertInertia(fn (Assert $page): Assert => $page->component('Platform/419'));
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    private function readPage(string $status): string
    {
        $contents = file_get_contents(resource_path("js/Pages/Platform/{$status}.vue"));

        $this->assertNotFalse($contents);

        return $contents;
    }
}
