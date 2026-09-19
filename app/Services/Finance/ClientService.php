<?php

namespace App\Services\Finance;

use App\Models\Finance\Client;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class ClientService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @return list<array{id: int, name: string, phone: string, contact: string|null, notes: string|null, is_active: bool, contracts_count: int}> */
    public function forManagement(): array
    {
        return Client::query()
            ->withCount('contracts')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Client $client): array => [
                'id' => (int) $client->getKey(),
                'name' => $client->name,
                'phone' => $client->phone,
                'contact' => $client->contact,
                'notes' => $client->notes,
                'is_active' => $client->is_active,
                'contracts_count' => $client->contracts_count,
            ])->all();
    }

    /** @param array{name: string, phone: string, contact?: string|null, notes?: string|null, is_active: bool} $data */
    public function create(array $data, User $actor): Client
    {
        $client = new Client($this->normalized($data));

        return DB::connection($client->getConnectionName())->transaction(function () use ($client, $actor): Client {
            $this->auditLogger->runExplicitly(
                auditable: $client,
                operation: fn (): bool => $client->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'client_created',
                newValues: $client->getAttributes(),
                reason: 'Création du client financier.',
            );

            return $client;
        });
    }

    /** @param array{name: string, phone: string, contact?: string|null, notes?: string|null, is_active: bool} $data */
    public function update(Client $client, array $data, User $actor): Client
    {
        return DB::connection($client->getConnectionName())->transaction(function () use ($client, $data, $actor): Client {
            $locked = Client::query()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();
            $oldValues = Arr::only($locked->getRawOriginal(), ['name', 'phone', 'contact', 'notes', 'is_active']);
            $locked->fill($this->normalized($data));

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'client_updated',
                oldValues: $oldValues,
                newValues: Arr::only($locked->getAttributes(), ['name', 'phone', 'contact', 'notes', 'is_active']),
                reason: 'Modification du client financier.',
            );

            return $locked;
        });
    }

    /** @param array{name: string, phone: string, contact?: string|null, notes?: string|null, is_active: bool} $data
     * @return array{name: string, phone: string, contact: string|null, notes: string|null, is_active: bool}
     */
    private function normalized(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'phone' => PhoneNumber::normalize($data['phone']),
            'contact' => $this->nullableText($data['contact'] ?? null),
            'notes' => $this->nullableText($data['notes'] ?? null),
            'is_active' => $data['is_active'],
        ];
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
