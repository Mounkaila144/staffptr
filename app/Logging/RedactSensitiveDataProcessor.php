<?php

namespace App\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Stringable;
use Throwable;

class RedactSensitiveDataProcessor implements ProcessorInterface
{
    private const MASK = '[MASQUÉ]';

    private const OMITTED = '[NON JOURNALISÉ]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'confirmationcode',
        'cookie',
        'password',
        'passwordconfirmation',
        'secret',
        'setcookie',
        'token',
    ];

    /** @var list<string> */
    private const PERSONAL_CONTENT_KEYS = [
        'address',
        'body',
        'content',
        'email',
        'firstname',
        'lastname',
        'name',
        'payload',
        'person',
        'phone',
        'request',
        'user',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $secrets = $this->sensitiveValues($record->context);
        $context = $this->sanitizeValue($record->context, $secrets);
        $extra = $this->sanitizeValue($record->extra, $secrets);

        if (! is_array($context) || ! is_array($extra)) {
            return $record;
        }

        $extra = array_merge($extra, $this->requestContext());

        return $record->with(
            message: $this->redactString($record->message, $secrets),
            context: $context,
            extra: $extra,
        );
    }

    /**
     * @param  array<mixed>  $context
     * @return list<string>
     */
    private function sensitiveValues(array $context): array
    {
        $values = [];
        $this->collectSensitiveValues($context, $values);

        $request = $this->request();

        if ($request !== null) {
            foreach (['password', 'password_confirmation', 'confirmation_code', 'token', 'secret'] as $key) {
                $this->appendScalarValues($request->input($key), $values);
            }

            $this->appendScalarValues($request->header('Authorization'), $values);

            foreach ($request->cookies->all() as $cookie) {
                $this->appendScalarValues($cookie, $values);
            }
        }

        $values = array_values(array_unique(array_filter(
            $values,
            static fn (string $value): bool => $value !== '',
        )));
        usort($values, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $values;
    }

    /**
     * @param  array<mixed>  $values
     * @param  list<string>  $secrets
     */
    private function collectSensitiveValues(array $values, array &$secrets): void
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $this->appendScalarValues($value, $secrets);

                continue;
            }

            if (is_array($value)) {
                $this->collectSensitiveValues($value, $secrets);
            }
        }
    }

    /** @param list<string> $secrets */
    private function appendScalarValues(mixed $value, array &$secrets): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->appendScalarValues($item, $secrets);
            }

            return;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            $secrets[] = (string) $value;
        }
    }

    /** @param list<string> $secrets */
    private function sanitizeValue(mixed $value, array $secrets, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::MASK;
        }

        if ($key !== null && $this->isPersonalContentKey($key)) {
            return self::OMITTED;
        }

        if ($value instanceof Throwable) {
            return $this->sanitizeException($value, $secrets);
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $itemKey => $item) {
                $sanitized[$itemKey] = $this->sanitizeValue(
                    $item,
                    $secrets,
                    is_string($itemKey) ? $itemKey : null,
                );
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->redactString($value, $secrets);
        }

        if (is_object($value)) {
            return ['object_type' => $value::class];
        }

        if (is_resource($value)) {
            return '[RESSOURCE]';
        }

        return $value;
    }

    /**
     * @param  list<string>  $secrets
     * @return array{class: string, message: string, code: int|string, file: string, line: int, trace: list<array<string, mixed>>, previous?: array<mixed>}
     */
    private function sanitizeException(Throwable $exception, array $secrets): array
    {
        $sanitized = [
            'class' => $exception::class,
            'message' => $this->redactString($exception->getMessage(), $secrets),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_map(
                fn (array $frame): array => $this->sanitizeTraceFrame($frame, $secrets),
                $exception->getTrace(),
            ),
        ];

        if ($exception->getPrevious() !== null) {
            $sanitized['previous'] = $this->sanitizeException($exception->getPrevious(), $secrets);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $frame
     * @param  list<string>  $secrets
     * @return array<string, mixed>
     */
    private function sanitizeTraceFrame(array $frame, array $secrets): array
    {
        $sanitized = array_intersect_key($frame, array_flip([
            'file',
            'line',
            'class',
            'type',
            'function',
        ]));

        if (isset($frame['args']) && is_array($frame['args'])) {
            $sanitized['args'] = array_map(
                fn (mixed $argument): string => $this->traceArgumentDescription($argument, $secrets),
                $frame['args'],
            );
        }

        return $sanitized;
    }

    /** @param list<string> $secrets */
    private function traceArgumentDescription(mixed $argument, array $secrets): string
    {
        if (is_string($argument) && $this->redactString($argument, $secrets) === self::MASK) {
            return self::MASK;
        }

        return match (true) {
            is_object($argument) => '[OBJET '.class_basename($argument).']',
            is_array($argument) => '[TABLEAU]',
            is_string($argument) => '[CHAÎNE]',
            is_resource($argument) => '[RESSOURCE]',
            default => '['.mb_strtoupper(get_debug_type($argument)).']',
        };
    }

    /** @param list<string> $secrets */
    private function redactString(string $value, array $secrets): string
    {
        return str_replace($secrets, self::MASK, $value);
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'cookie');
    }

    private function isPersonalContentKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        return in_array($normalized, self::PERSONAL_CONTENT_KEYS, true);
    }

    /** @return array{request_id: string, route: string|null, user_id?: int|string} */
    private function requestContext(): array
    {
        $request = $this->request();

        if ($request === null) {
            return [
                'request_id' => (string) Str::uuid(),
                'route' => null,
            ];
        }

        $requestId = (string) $request->attributes->get('request_id', (string) Str::uuid());
        $request->attributes->set('request_id', $requestId);
        $route = $request->route();
        $context = [
            'request_id' => $requestId,
            'route' => $route?->getName() ?? $route?->uri(),
        ];
        $user = $request->user();

        if ($user !== null) {
            $context['user_id'] = $user->getAuthIdentifier();
        }

        return $context;
    }

    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        return app('request');
    }
}
