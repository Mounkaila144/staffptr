<?php

namespace App\Services\Platform;

use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class EvolutionApiClient
{
    /** @throws EvolutionApiUnavailable */
    public function sendPasswordResetConfirmation(string $phone, string $confirmationCode): void
    {
        $instance = $this->configuredString('instance');

        try {
            $connection = $this->request()->get('/instance/connectionState/'.rawurlencode($instance));
            $state = data_get($connection->json(), 'instance.state', data_get($connection->json(), 'state'));

            if (! $connection->successful() || $state !== 'open') {
                throw new EvolutionApiUnavailable;
            }

            $response = $this->request()->post('/message/sendText/'.rawurlencode($instance), [
                'number' => PhoneNumber::forEvolutionApi($phone),
                'text' => "PTR Staff — code de confirmation pour la réinitialisation : {$confirmationCode}. Valable 10 minutes. Ne le partagez qu’avec la personne qui effectue l’opération.",
            ]);

            if ($response->status() !== 201) {
                throw new EvolutionApiUnavailable;
            }
        } catch (ConnectionException) {
            throw new EvolutionApiUnavailable;
        }
    }

    /** @throws EvolutionApiUnavailable */
    private function request(): PendingRequest
    {
        $url = rtrim($this->configuredString('url'), '/');
        $timeout = config('services.evolution.timeout_seconds', 5);

        if ((! app()->environment(['local', 'testing']) && parse_url($url, PHP_URL_SCHEME) !== 'https')
            || ! is_int($timeout) || $timeout < 1) {
            throw new EvolutionApiUnavailable;
        }

        return Http::baseUrl($url)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['apikey' => $this->configuredString('key')])
            ->timeout($timeout);
    }

    /** @throws EvolutionApiUnavailable */
    private function configuredString(string $key): string
    {
        $value = config("services.evolution.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw new EvolutionApiUnavailable;
        }

        return trim($value);
    }
}
