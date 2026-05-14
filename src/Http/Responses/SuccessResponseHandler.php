<?php

namespace Ylsalame\LaravelUseCases\Http\Responses;

use Illuminate\Http\JsonResponse;

interface SuccessResponseHandler
{
    public function handle(mixed $data): JsonResponse;
}
