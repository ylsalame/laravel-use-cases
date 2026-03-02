<?php

namespace Ylsalame\LaravelUseCases\Exceptions;

use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Interface for custom error handlers that receive UseCase error data.
 * Implement this when using error_handler_mode = 'custom'.
 */
interface UseCaseExceptionHandler
{
    /**
     * Handle the exception and return a JSON response.
     *
     * @param  mixed  $data  The data that was passed to the UseCase (route params + request input)
     */
    public function handle(Throwable $exception, mixed $data): JsonResponse;
}
