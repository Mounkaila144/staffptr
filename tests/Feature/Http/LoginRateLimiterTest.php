<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class LoginRateLimiterTest extends TestCase
{
    public function test_ac_3_login_limiter_accepts_five_attempts_then_blocks_for_one_minute(): void
    {
        $this->freezeTime();
        $url = route('testing.login-rate-limit', ['phone' => '+22790000000']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->get($url)->assertNoContent();
        }

        $this->get($url)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60')
            ->assertHeader('X-RateLimit-Limit', '5');
    }

    public function test_ac_3_login_limiter_separates_credentials_without_storing_the_phone_as_its_key(): void
    {
        $firstPhone = route('testing.login-rate-limit', ['phone' => '+22790000000']);
        $secondPhone = route('testing.login-rate-limit', ['phone' => '+22790000001']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->get($firstPhone)->assertNoContent();
        }

        $this->get($firstPhone)->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.22'])
            ->get($secondPhone)
            ->assertNoContent();
    }

    public function test_ac_3_login_limiter_blocks_a_second_phone_from_the_same_address(): void
    {
        $firstPhone = route('testing.login-rate-limit', ['phone' => '+22790000000']);
        $secondPhone = route('testing.login-rate-limit', ['phone' => '+22790000001']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->get($firstPhone)->assertNoContent();
        }

        $this->get($secondPhone)->assertTooManyRequests();
    }
}
