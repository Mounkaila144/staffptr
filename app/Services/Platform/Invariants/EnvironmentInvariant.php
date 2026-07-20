<?php

namespace App\Services\Platform\Invariants;

class EnvironmentInvariant implements InvariantCheck
{
    private const NAME = 'Cohérence APP_ENV / APP_DEBUG';

    public function check(): InvariantResult
    {
        $environment = config('app.env');
        $debug = config('app.debug');

        if (! is_string($environment) || ! is_bool($debug)) {
            return InvariantResult::fail(
                self::NAME,
                'configuration absente ou illisible',
                'un environnement connu avec une valeur APP_DEBUG cohérente',
            );
        }

        $expectedDebug = match ($environment) {
            'local', 'testing' => true,
            'staging', 'production' => false,
            default => null,
        };
        $observed = "APP_ENV={$environment}, APP_DEBUG=".($debug ? 'true' : 'false');

        if ($expectedDebug === null) {
            return InvariantResult::fail(
                self::NAME,
                $observed,
                'APP_ENV parmi local, testing, staging ou production',
            );
        }

        $expected = "APP_ENV={$environment}, APP_DEBUG=".($expectedDebug ? 'true' : 'false');

        return $debug === $expectedDebug
            ? InvariantResult::pass(self::NAME, $observed, $expected)
            : InvariantResult::fail(self::NAME, $observed, $expected);
    }
}
