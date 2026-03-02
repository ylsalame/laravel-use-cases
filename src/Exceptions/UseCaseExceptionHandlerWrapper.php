<?php

namespace Ylsalame\LaravelUseCases\Exceptions;

use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Interface for custom error handler wrappers.
 * Implement this when using error_handler_mode = 'wrapper'.
 * The package's error handler produces the response, then passes it to your wrapper
 * so you can modify or wrap it before returning.
 */
interface UseCaseExceptionHandlerWrapper
{
    public function handle(JsonResponse $packageResponse, Throwable $exception, mixed $data): JsonResponse;
}
