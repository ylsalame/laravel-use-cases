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
     * Execute the use case with automatic transaction handling and validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): mixed
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

    /**
     * The actual use case logic - to be implemented by concrete classes.
     *
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    abstract protected function execute(array $data);
}

