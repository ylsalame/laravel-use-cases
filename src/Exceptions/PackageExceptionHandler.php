<?php

namespace Ylsalame\LaravelUseCases\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Ylsalame\LaravelUseCases\Support\UseCaseExecutionContext;

/**
 * Default package error handler for UseCase API errors.
 * Based on ApiException pattern - handles common exception types and returns structured JSON.
 */
class PackageExceptionHandler implements UseCaseExceptionHandler
{
    public function handle(Throwable $exception, mixed $data): JsonResponse
    {
        try {
            $requestId = request()->header('X-Request-ID') ?? uniqid();
            $exceptionCode = $this->getExceptionCode($exception);

            $routeParameters = [];
            $requestParameters = [];
            if (request()->route()) {
                $routeParameters = request()->route()->parameters();
                $requestParameters = request()->all();
            }

            foreach ($requestParameters as &$requestParameterValue) {
                if (is_object($requestParameterValue)) {
                    $requestParameterValue = '<object>';
                }
            }

            $stackTrace = $exception->getTrace();
            $formattedTrace = [];
            foreach ($stackTrace as $key => $stackPoint) {
                $trace = "#$key ";
                $trace .= ($stackPoint['file'] ?? 'unknown file');
                $trace .= ' ) @LINE ' . ($stackPoint['line'] ?? 'unknown line') . ': ';
                $trace .= (isset($stackPoint['class']) ? $stackPoint['class'] . '->' : '');
                $trace .= $stackPoint['function'] . '()';
                $formattedTrace[] = $trace;
            }

            $errorData = [
                'timestamp' => microtime(true),
                'environment' => config('app.env'),
                'routeAction' => Route::currentRouteAction(),
                'currentUrl' => url()->current(),
                'requestMethod' => request()->method(),
                'exceptionType' => get_class($exception),
                'msg' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
                'methodData' => json_encode($data),
                'routeParameters' => json_encode($routeParameters),
                'requestParameters' => json_encode($requestParameters),
                'statusCode' => $exceptionCode,
                'trace' => $formattedTrace,
                'useCaseExecutions' => UseCaseExecutionContext::all(),
            ];

            $errorDataString = '';
            foreach ($errorData as $key => $value) {
                if ($errorDataString !== '') {
                    $errorDataString .= ' || ';
                }
                $errorDataString .= $key . ': ' . (is_array($value) ? json_encode($value) : $value);
            }
            Log::error($errorDataString);

            if (config('app.debug', false) === false) {
                $errorData = [];
            }

            $responseData = $this->buildResponseData('failure', $exceptionCode, $requestId, $errorData);

            if ($exception instanceof HttpResponseException) {
                if (isset($data['errors'])) {
                    $responseData['errors'] = $data['errors'];
                }
                return response()->json($responseData, $exceptionCode, [], JSON_PRETTY_PRINT);
            }

            if ($exception instanceof ValidationException) {
                $responseData['errors'] = $exception->errors();
                return response()->json($responseData, $exceptionCode, [], JSON_PRETTY_PRINT);
            }

            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                $responseData['message'] = 'unauthorized';
                return response()->json($responseData, $exceptionCode, [], JSON_PRETTY_PRINT);
            }

            if ($exception instanceof NotFoundHttpException) {
                $responseData['message'] = $exception->getMessage();
                return response()->json($responseData, $exceptionCode, [], JSON_PRETTY_PRINT);
            }

            if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $responseData['message'] = 'Target record not found';
                return response()->json($responseData, $exceptionCode, [], JSON_PRETTY_PRINT);
            }

            if ($exception instanceof HttpException) {
                $responseData['message'] = $exception->getMessage();
                return response()->json($responseData, $exceptionCode, [], JSON_PRETTY_PRINT);
            }

            $responseData['message'] = 'An unexpected error has happened';
            return response()->json($responseData, $exceptionCode, [], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            $requestId = request()->header('X-Request-ID') ?? uniqid();
            return response()->json([
                'status' => 'failure',
                'statusCode' => 500,
                'message' => 'Error code A-0034',
                'data' => [],
                'requestId' => $requestId,
                'errorData' => [$e->getMessage()],
            ], 500, [], JSON_PRETTY_PRINT);
        }
    }

    private function getExceptionCode(Throwable $exception): int
    {
        if ($exception instanceof NotFoundHttpException) {
            return 404;
        }
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
            || $exception instanceof \Illuminate\Http\Exceptions\HttpResponseException
            || $exception instanceof ValidationException) {
            return 422;
        }
        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            return 401;
        }
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    /**
     * @param  array<string, mixed>  $errorData
     * @return array<string, mixed>
     */
    private function buildResponseData(string $status, int $statusCode, string $requestId, array $errorData): array
    {
        $data = [
            'status' => $status,
            'statusCode' => $statusCode,
            'data' => null,
            'requestId' => $requestId,
            'timestamp' => microtime(true),
            'errorData' => $errorData,
        ];

        if (config('app.debug') === true) {
            $data['debug'] = true;
        }

        return $data;
    }
}
