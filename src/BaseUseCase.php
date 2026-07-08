<?php

namespace Ylsalame\LaravelUseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Ylsalame\LaravelUseCases\Support\ClassInferenceHelper;
use Ylsalame\LaravelUseCases\Support\UseCaseExecutionContext;

abstract class BaseUseCase
{
    protected bool $isInternalUseCase = false;

    /**
     * When true, the result of execute() will be transformed
     * using the inferred Resource class when handle() is used.
     * Default is true so callers get Resource-shaped data by default.
     */
    protected bool $autoResource = true;

    /**
     * Execute the use case with automatic transaction handling, validation
     * and (optionally) resource transformation.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): mixed
    {
        $result = $this->handleWithoutResource($data);

        if (! $this->shouldTransformWithResource()) {
            return $result;
        }

        return $this->transformWithResource($result);
    }

    /**
     * Execute the use case with validation and transaction handling,
     * but without applying any Resource transformation.
     *
     * This is primarily intended for internal use (e.g. by the
     * package BaseController) when the caller wants to control
     * how Resources are applied.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleWithoutResource(array $data): mixed
    {
        UseCaseExecutionContext::add(static::class, $data);

        $validatedData = $this->validateWithFormRequest($data);

        if (DB::transactionLevel() > 0) {
            return $this->execute($validatedData);
        }

        return DB::transaction(function () use ($validatedData) {
            return $this->execute($validatedData);
        });
    }

    /**
     * Validate data using the corresponding FormRequest.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validateWithFormRequest(array $data): array
    {
        $groupAndAction = ClassInferenceHelper::inferGroupAndActionFromUseCase(static::class);
        $formRequestClass = ClassInferenceHelper::buildFormRequestClass($groupAndAction['group'], $groupAndAction['action']);

        // Internal UseCases are exempt from needing a FormRequest. When one exists anyway
        // (e.g. an internal UseCase that also exposes an endpoint), validate as normal.
        // When none exists, the caller is trusted code — return the data as-is.
        if ($this->isInternalUseCase && ! class_exists($formRequestClass)) {
            return $data;
        }

        Log::info('BaseUseCase - validating with FormRequest: ' . $formRequestClass);

        /** @var \Illuminate\Foundation\Http\FormRequest $formRequest */
        $formRequest = new $formRequestClass();
        $formRequest->merge($data);

        $rules = collect($formRequest->rules())->map(function ($rule) {
            if (is_string($rule)) {
                return 'bail|' . $rule;
            }

            if (is_array($rule)) {
                return array_merge(['bail'], $rule);
            }

            return $rule;
        })->all();

        $validator = Validator::make(
            $formRequest->all(),
            $rules,
            $formRequest->messages(),
            $formRequest->attributes()
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function isInternalUseCase(): bool
    {
        return $this->isInternalUseCase;
    }

    protected function shouldTransformWithResource(): bool
    {
        return $this->autoResource;
    }

    /**
     * Apply the inferred Resource class (if available) to the given result
     * and return the Resource's array representation. If no matching
     * Resource exists, the original result is returned unchanged.
     */
    protected function transformWithResource(mixed $result): mixed
    {
        $groupAndAction = ClassInferenceHelper::inferGroupAndActionFromUseCase(static::class);
        $resourceClass = ClassInferenceHelper::buildResourceClass($groupAndAction['group'], $groupAndAction['action']);

        if (! class_exists($resourceClass)) {
            return $result;
        }

        $resource = new $resourceClass($result);

        if (! method_exists($resource, 'toArray')) {
            return $resource;
        }

        /** @var array<string, mixed>|null $array */
        $array = $resource->toArray(request());

        return $array === null ? $resource : $array;
    }

    /**
     * The actual use case logic - to be implemented by concrete classes.
     *
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    abstract protected function execute(array $data);
}

