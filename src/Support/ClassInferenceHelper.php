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

        // $actionName is something like "App\Http\Controllers\Web\ModelScriptsController@index"
        [$controllerFqcn, $method] = explode('@', $actionName);

        // Strip the base controllers namespace to get the relative path, e.g. "Web\ModelScriptsController"
        $relative = ltrim(str_replace('App\\Http\\Controllers\\', '', $controllerFqcn), '\\');
        $parts = $relative !== '' ? explode('\\', $relative) : [];

        if (empty($parts)) {
            throw new RuntimeException('Unable to infer controller group from route action: ' . $actionName);
        }

        $controllerShort = array_pop($parts); // e.g. "ModelScriptsController"

        // Base namespace segments (e.g. ["Web"] or ["Api", "v1"])
        $prefixParts = $parts;

        // Remove "Controller" suffix from the short name
        $controllerBase = substr($controllerShort, 0, -10);

        // Build group from sub-namespaces plus controller name, e.g. "Web\ModelScript" or "System\Project"
        $groupParts = array_map(
            static fn (string $part): string => ucfirst($part),
            array_filter($prefixParts)
        );
        $groupParts[] = ucfirst($controllerBase);
        $group = implode('\\', $groupParts);

        $action = ucfirst($controllerBase) . ucfirst($method);

        return [
            'group' => $group,
            'action' => $action,
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

        $namespaceParts = explode('\\', trim($useCaseClass, '\\'));

        // Find the "UseCases" segment so we can treat everything after it (except the class)
        // as the group path. This allows nested groups like Web\ModelScript, Api\v1\Project, etc.
        $useCasesIndex = array_search('UseCases', $namespaceParts, true);

        if ($useCasesIndex === false) {
            // Fallback to previous behaviour: single-segment group if structure is unexpected
            $group = $namespaceParts[2] ?? '';
        } else {
            $groupParts = array_slice($namespaceParts, $useCasesIndex + 1, -1);
            $group = implode('\\', $groupParts);
        }

        return [
            'group' => $group,
            'action' => $classWithoutSuffix,
        ];
    }

    public static function buildFormRequestClass(string $group, string $action): string
    {
        $requestsNamespace = Config::get('laravel-use-cases.requests_namespace', 'App\\Http\\Requests');

        return $requestsNamespace . '\\' . $group . '\\' . $action . 'Request';
    }

    public static function buildUseCaseClass(string $group, string $action): string
    {
        $useCasesNamespace = Config::get('laravel-use-cases.use_cases_namespace', 'App\\UseCases');

        return $useCasesNamespace . '\\' . $group . '\\' . $action . 'UseCase';
    }

    public static function buildResourceClass(string $group, string $action): string
    {
        $resourcesNamespace = Config::get('laravel-use-cases.resources_namespace', 'App\\Http\\Resources');

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
     * Checks each directory listed in laravel-use-cases.source_directories in order,
     * returning the first path where the file actually exists. Falls back to the
     * primary directory path when no match is found.
     */
    public static function classNameToFilePath(string $className): string
    {
        $rootNamespace = config('laravel-use-cases.root_namespace', 'App');
        $sourceDirs = config('laravel-use-cases.source_directories', ['app']);

        $relativePath = str_replace($rootNamespace . '\\', '', $className);
        if (str_starts_with($relativePath, $rootNamespace . '\\Tests\\') || str_starts_with($relativePath, 'Tests\\')) {
            $relativePath = preg_replace('/^(' . preg_quote($rootNamespace, '/') . '\\\\)?Tests\\\\/', 'tests\\\\', $relativePath);
            $relativePath = str_replace('\\', '/', $relativePath);
            return base_path($relativePath . '.php');
        }
        $relativePath = str_replace('\\', '/', $relativePath);

        $primaryPath = base_path($sourceDirs[0] . '/' . $relativePath . '.php');

        foreach ($sourceDirs as $dir) {
            $candidate = base_path($dir . '/' . $relativePath . '.php');
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return $primaryPath;
    }
}

