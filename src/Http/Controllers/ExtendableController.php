<?php

namespace Ylsalame\LaravelUseCases\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Ylsalame\LaravelUseCases\BaseUseCase;
use Ylsalame\LaravelUseCases\Support\ClassInferenceHelper;

class ExtendableController extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    private string $executionGroup;

    private string $executionAction;

    private string $requiredRequestClass;

    private string $requiredUseCaseClass;

    private string $requiredResourceClass;

    private ?string $requiredTestClass = null;

    private mixed $useCaseResult;

    private mixed $resourceOutput;

    /**
     * Magic method handler for all endpoint methods.
     *
     * @param  array<mixed>  $parameters
     */
    public function __call($method, mixed $parameters): JsonResponse
    {
        try {
            Log::info('ExtendableController - magic __call for ' . $method);

            $this->getInferredGroupAndAction();
            $this->parseAndValidateRequiredFiles();

            $this->triggerUseCaseExecution();
            $this->triggerResourceOutput();

            return response()->json($this->resourceOutput);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('ExtendableController - unhandled exception', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Unexpected error while processing request.',
            ], 500);
        }
    }

    public function getInferredGroupAndAction(): void
    {
        Log::info('ExtendableController - getInferredGroupAndAction');

        $groupAndAction = ClassInferenceHelper::inferGroupAndActionFromRoute();

        $this->executionGroup = $groupAndAction['group'];
        $this->executionAction = $groupAndAction['action'];

        Log::info('ExtendableController - inferred', [
            'group' => $this->executionGroup,
            'action' => $this->executionAction,
        ]);
    }

    private function parseAndValidateRequiredFiles(): void
    {
        Log::info('ExtendableController - parseAndValidateRequiredFiles');

        $this->requiredRequestClass = ClassInferenceHelper::buildFormRequestClass(
            $this->executionGroup,
            $this->executionAction
        );
        $this->requiredUseCaseClass = ClassInferenceHelper::buildUseCaseClass(
            $this->executionGroup,
            $this->executionAction
        );
        $this->requiredResourceClass = ClassInferenceHelper::buildResourceClass(
            $this->executionGroup,
            $this->executionAction
        );

        if (config('use_cases.require_test_class')) {
            $rootNamespace = config('use_cases.root_namespace', 'App');
            $this->requiredTestClass = $rootNamespace . '\\Tests\\Feature\\' . $this->executionGroup . '\\' . $this->executionAction . 'Test';
        }

        $requiredClasses = [
            'Request' => $this->requiredRequestClass,
            'UseCase' => $this->requiredUseCaseClass,
            'Resource' => $this->requiredResourceClass,
        ];

        if ($this->requiredTestClass !== null) {
            $requiredClasses['Test'] = $this->requiredTestClass;
        }

        foreach ($requiredClasses as $type => $requiredClass) {
            $filePath = ClassInferenceHelper::classNameToFilePath($requiredClass);

            if (! file_exists($filePath)) {
                throw new RuntimeException(
                    'ExtendableController error: ' . $type . ' file does not exist at ' . $filePath
                );
            }

            if (! class_exists($requiredClass)) {
                throw new RuntimeException(
                    'ExtendableController error: ' . $type . ' class ' . $requiredClass . ' cannot be loaded'
                );
            }
        }
    }

    private function triggerUseCaseExecution(): void
    {
        Log::info('ExtendableController - triggerUseCaseExecution');

        $useCase = app()->make($this->requiredUseCaseClass);

        if (! $useCase instanceof BaseUseCase) {
            throw new RuntimeException('Resolved use case does not extend BaseUseCase: ' . $this->requiredUseCaseClass);
        }

        $parameters = array_merge(
            request()->route()?->parameters() ?? [],
            request()->all()
        );

        if ($useCase->isInternalUseCase()) {
            throw new RuntimeException('This UseCase is internal-only and cannot be accessed from an endpoint.');
        }

        $this->useCaseResult = $useCase->handle($parameters);
    }

    private function triggerResourceOutput(): void
    {
        Log::info('ExtendableController - triggerResourceOutput');

        $isCollection = false;
        $isPaginated = false;

        if (! $this->isDependencyMethod()) {
            if ($this->useCaseResult instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                $isPaginated = true;
                $isCollection = true;
            } elseif (is_array($this->useCaseResult) && (empty($this->useCaseResult) || isset($this->useCaseResult[0]))) {
                $isCollection = true;
            } elseif (is_object($this->useCaseResult) && method_exists($this->useCaseResult, 'count')) {
                $isCollection = true;
            }
        }

        if ($isCollection) {
            if ($isPaginated) {
                /** @var \Illuminate\Pagination\LengthAwarePaginator<int, mixed> $paginator */
                $paginator = $this->useCaseResult;
                $paginatorArray = $paginator->toArray();

                $transformedItems = [];
                foreach ($paginator->items() as $item) {
                    $resource = new $this->requiredResourceClass($item);
                    if (method_exists($resource, 'toArray')) {
                        $transformedItems[] = $resource->toArray(request());
                    } else {
                        $transformedItems[] = $resource;
                    }
                }

                $paginatorArray['data'] = $transformedItems;

                $this->resourceOutput = $paginatorArray;
            } else {
                if (class_exists($this->requiredResourceClass) && method_exists($this->requiredResourceClass, 'collection')) {
                    $this->resourceOutput = $this->requiredResourceClass::collection($this->useCaseResult);
                } else {
                    throw new RuntimeException('Resource class does not exist or does not have collection method: ' . $this->requiredResourceClass);
                }
            }
        } else {
            $resource = new $this->requiredResourceClass($this->useCaseResult);

            if (! method_exists($resource, 'toArray')) {
                throw new RuntimeException('Resource does not have toArray method: ' . $this->requiredResourceClass);
            }

            $tempArray = $resource->toArray(request());

            $this->resourceOutput = $tempArray === null ? null : $resource;
        }
    }

    private function isDependencyMethod(): bool
    {
        return str_ends_with($this->executionAction, 'Dependencies');
    }
}

