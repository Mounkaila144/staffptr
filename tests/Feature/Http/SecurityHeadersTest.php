<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Vite;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_ac_1_and_2_every_http_response_has_the_exact_security_headers_without_hsts(): void
    {
        $response = $this->get(route('platform.demo'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function test_ac_1_https_responses_have_the_exact_hsts_value(): void
    {
        $this->get('https://localhost/__test/interface-demo')
            ->assertOk()
            ->assertHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
    }

    public function test_ac_2_content_policy_is_local_only_strict_and_uses_a_fresh_nonce(): void
    {
        $firstPolicy = (string) $this->get(route('platform.demo'))->headers->get('Content-Security-Policy');
        $secondPolicy = (string) $this->get(route('platform.demo'))->headers->get('Content-Security-Policy');

        foreach ([
            "default-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
        ] as $directive) {
            $this->assertStringContainsString($directive, $firstPolicy);
        }

        $this->assertStringNotContainsString('unsafe-inline', $firstPolicy);
        $this->assertStringNotContainsString('http:', $firstPolicy);
        $this->assertStringNotContainsString('https:', $firstPolicy);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+';/", $firstPolicy.';');
        $this->assertNotSame($firstPolicy, $secondPolicy);
    }

    public function test_ac_2_vite_script_tags_receive_the_policy_nonce(): void
    {
        $buildDirectory = 'build-security-test-'.bin2hex(random_bytes(4));
        $manifestDirectory = public_path($buildDirectory);
        $manifestPath = $manifestDirectory.'/manifest.json';

        $this->assertTrue(mkdir($manifestDirectory, 0755, true));
        $this->assertNotFalse(file_put_contents($manifestPath, json_encode([
            'resources/css/app.css' => [
                'file' => 'assets/app.css',
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app.js',
                'src' => 'resources/js/app.js',
                'isEntry' => true,
            ],
        ], JSON_THROW_ON_ERROR)));
        $this->withVite();
        $vite = app(Vite::class);
        $vite->useBuildDirectory($buildDirectory);

        try {
            $response = $this->get(route('platform.demo'));
            $policy = (string) $response->headers->get('Content-Security-Policy');

            preg_match("/'nonce-([^']+)'/", $policy, $matches);

            $this->assertNotEmpty($matches[1] ?? null);
            $response->assertSee('nonce="'.$matches[1].'"', false);
        } finally {
            $vite->useBuildDirectory('build');
            @unlink($manifestPath);
            @rmdir($manifestDirectory);
        }
    }

    public function test_ac_2_inertia_does_not_inject_its_inline_progress_style(): void
    {
        $application = file_get_contents(resource_path('js/app.js'));

        $this->assertNotFalse($application);
        $this->assertStringContainsString('progress: false', $application);
    }

    public function test_ac_2_health_endpoint_is_not_exempt_from_hardening(): void
    {
        $this->get(route('health'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }
}
