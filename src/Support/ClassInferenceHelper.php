<?php

namespace Ylsalame\LaravelUseCases\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class ClassInferenceHelper
{
    /**
     * Infer the group and action from the current route.
     *
     * @return array{group: string, action: string}
     */
    public static function inferGroupAndActionFromRoute(): array
    {
        $route = Route::getCurrentRoute();

        if (! $route) {
            throw new RuntimeException('Request route not reachable in this context');
        }

        $actionName = $route->getActionName();

        if ($actionName === '') {
            throw new RuntimeException('Request route action not reachable in this context');
        }

        $controllerAction = class_basename($actionName);
        [$controller, $action] = explode('@', $controllerAction);

        // Remove "Controller" suffix
        $controller = substr($controller, 0, -10);

        // Pluralization handling (e.g. "ProjectsController" -> "Project")
        if (substr($controller, -3) === 'ies') {
            $controller = substr($controller, 0, -3) . 'y';
        }

        if (substr($controller, -1) === 's') {
            $controller = substr($controller, 0, -1);
        }

        return [
            'group' => ucfirst($controller),
            'action' => ucfirst($controller) . ucfirst($action),
        ];
    }

    /**
     * Infer the group and action from a UseCase class name.
     *
     * @return array{group: string, action: string}
     */
    public static function inferGroupAndActionFromUseCase(string $useCaseClass): array
    {
        $className = class_basename($useCaseClass);
        $classWithoutSuffix = preg_replace('/UseCase$/', '', $className);

        $namespaceParts = explode('\\', $useCaseClass);
        $group = $namespaceParts[2] ?? '';

        return [
            'group' => $group,
            'action' => $classWithoutSuffix,
        ];
    }

    public static function buildFormRequestClass(string $group, string $action): string
    {
        $requestsNamespace = Config::get('use_cases.requests_namespace', 'App\\Http\\Requests');

        return $requestsNamespace . '\\' . $group . '\\' . $action . 'Request';
    }

    public static function buildUseCaseClass(string $group, string $action): string
    {
        $useCasesNamespace = Config::get('use_cases.use_cases_namespace', 'App\\UseCases');

        return $useCasesNamespace . '\\' . $group . '\\' . $action . 'UseCase';
    }

    public static function buildResourceClass(string $group, string $action): string
    {
        $resourcesNamespace = Config::get('use_cases.resources_namespace', 'App\\Http\\Resources');

        $specificResourceClass = $resourcesNamespace . '\\' . $group . '\\' . $action . 'Resource';

        if (class_exists($specificResourceClass)) {
            return $specificResourceClass;
        }

        if (str_ends_with($action, 'Dependencies')) {
            return $resourcesNamespace . '\\General\\DependenciesResource';
        }

        if (str_ends_with($action, 'Delete') || str_ends_with($action, 'Update')) {
            return $resourcesNamespace . '\\General\\NullResource';
        }

        if (str_ends_with($action, 'Create')) {
            return $resourcesNamespace . '\\General\\SingleUuidResource';
        }

        return $specificResourceClass;
    }

    /**
     * Convert a class name to its corresponding file path.
     */
    public static function classNameToFilePath(string $className): string
    {
        $relativePath = str_replace('App\\', 'app\\', $className);
        $relativePath = str_replace('Tests\\', 'tests\\', $relativePath);
        $relativePath = str_replace('\\', '/', $relativePath);

        return base_path($relativePath . '.php');
    }
}

