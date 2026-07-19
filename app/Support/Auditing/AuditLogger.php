<?php

namespace App\Support\Auditing;

use App\Models\Platform\AuditLog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AuditLogger
{
    public function __construct(private readonly AuditContext $context) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        ?int $actorId,
        string $actorLabel,
        Model $auditable,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        $connection = $auditable->getConnection();

        if ($connection->transactionLevel() < 1) {
            throw new RuntimeException("L'audit doit être écrit dans une transaction active.");
        }

        $auditLog = new AuditLog;
        $auditLog->setConnection($auditable->getConnectionName());
        $auditLog->fill([
            'actor_id' => $this->actorId($actorId),
            'actor_label' => $this->requiredString($actorLabel, 120, 'actor_label'),
            'occurred_at' => CarbonImmutable::now('UTC'),
            'auditable_type' => $this->requiredString($auditable->getMorphClass(), 120, 'auditable_type'),
            'auditable_id' => $this->auditableId($auditable),
            'action' => $this->requiredString($action, 60, 'action'),
            'old_values' => $this->withoutSecrets($oldValues),
            'new_values' => $this->withoutSecrets($newValues),
            'reason' => $reason,
            'ip_address' => $this->packedIpAddress($ipAddress ?? $this->requestIpAddress()),
            'user_agent' => $this->userAgent($userAgent ?? $this->requestUserAgent()),
            'request_id' => $this->requestId($requestId),
        ]);
        $auditLog->saveOrFail();

        return $auditLog;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @return TResult
     */
    public function runExplicitly(
        Model $auditable,
        Closure $operation,
        ?int $actorId,
        string $actorLabel,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): mixed {
        if ($auditable->getConnection()->transactionLevel() < 1) {
            throw new RuntimeException("L'opération auditée doit être exécutée dans une transaction active.");
        }

        $this->context->suppressAutomaticAudit($auditable);

        try {
            $result = $operation();
            $this->record(
                actorId: $actorId,
                actorLabel: $actorLabel,
                auditable: $auditable,
                action: $action,
                oldValues: $oldValues,
                newValues: $newValues ?? $auditable->getAttributes(),
                reason: $reason,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            return $result;
        } finally {
            $this->context->releaseAutomaticAudit($auditable);
        }
    }

    private function actorId(?int $actorId): ?int
    {
        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException('actor_id doit être un entier positif ou null.');
        }

        return $actorId;
    }

    private function auditableId(Model $auditable): ?int
    {
        $identifier = $auditable->getKey();

        if ($identifier === null) {
            return null;
        }

        if (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier))) {
            return (int) $identifier;
        }

        throw new InvalidArgumentException("L'identifiant audité doit être un entier positif ou null.");
    }

    private function requiredString(string $value, int $maximumLength, string $field): string
    {
        if ($value === '' || mb_strlen($value) > $maximumLength) {
            throw new InvalidArgumentException("{$field} doit contenir entre 1 et {$maximumLength} caractères.");
        }

        return $value;
    }

    private function requestId(?string $requestId): string
    {
        $candidate = $requestId ?? $this->requestHeader('X-Request-ID') ?? Str::uuid()->toString();

        return $this->requiredString($candidate, 64, 'request_id');
    }

    private function packedIpAddress(?string $ipAddress): ?string
    {
        if ($ipAddress === null) {
            return null;
        }

        $packed = inet_pton($ipAddress);

        if ($packed === false) {
            throw new InvalidArgumentException("L'adresse IP du contexte d'audit est invalide.");
        }

        return $packed;
    }

    private function userAgent(?string $userAgent): ?string
    {
        return $userAgent === null ? null : mb_substr($userAgent, 0, 255);
    }

    /**
     * Password material never belongs in the permanent audit trail, even when a caller passes it.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function withoutSecrets(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $safe = [];

        foreach ($values as $key => $value) {
            if (str_contains(mb_strtolower((string) $key), 'password')) {
                continue;
            }

            $safe[$key] = is_array($value)
                ? $this->withoutSecrets($value)
                : $value;
        }

        return $safe;
    }

    private function requestIpAddress(): ?string
    {
        return app()->bound('request') ? request()->ip() : null;
    }

    private function requestUserAgent(): ?string
    {
        return app()->bound('request') ? request()->userAgent() : null;
    }

    private function requestHeader(string $header): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $value = request()->header($header);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
