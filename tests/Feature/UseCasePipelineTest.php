<?php

declare(strict_types=1);

namespace Ylsalame\LaravelUseCases\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Ylsalame\LaravelUseCases\Providers\LaravelUseCasesServiceProvider;

class UseCasePipelineTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelUseCasesServiceProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        $router->get('/dummy', [\App\Http\Controllers\DummyController::class, 'index']);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('laravel-use-cases.require_test_class', false);
    }

    public function test_use_case_pipeline_executes_and_transforms_response(): void
    {
        $response = $this->getJson('/dummy?name=John');

        $response->assertStatus(200);
        $response->assertJson([
            'greeting' => 'Hello John',
        ]);
    }
}

namespace App\Http\Controllers;

use Ylsalame\LaravelUseCases\Http\Controllers\ExtendableController;

class DummyController extends ExtendableController
{
}

namespace App\Http\Requests\Dummy;

use Illuminate\Foundation\Http\FormRequest;

class DummyIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }
}

namespace App\UseCases\Dummy;

use Ylsalame\LaravelUseCases\BaseUseCase;

class DummyIndexUseCase extends BaseUseCase
{
    protected function execute(array $data)
    {
        return [
            'greeting' => 'Hello ' . $data['name'],
        ];
    }
}

namespace App\Http\Resources\Dummy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DummyIndexResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return $this->resource;
    }
}

