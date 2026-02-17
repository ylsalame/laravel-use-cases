<?php

namespace Ylsalame\LaravelUseCases\Support;

class UseCaseExecutionContext
{
    private static array $executions = [];

    public static function clear(): void
    {
        self::$executions = [];
    }

    public static function add(string $useCaseClass, array $parameters): void
    {
        self::$executions[] = [
            'useCase' => $useCaseClass,
            'parameters' => self::sanitizeValue($parameters),
            'timestamp' => microtime(true),
        ];
    }

    public static function all(): array
    {
        return self::$executions;
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitizeValue'], $value);
        }

        if (is_object($value)) {
            return sprintf('<object:%s>', get_class($value));
        }

        if (is_resource($value)) {
            return '<resource>';
        }

        return $value;
    }
}

