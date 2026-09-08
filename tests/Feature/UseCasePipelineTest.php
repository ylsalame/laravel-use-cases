<?php

declare(strict_types=1);

namespace Ylsalame\LaravelUseCases\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Ylsalame\LaravelUseCases\Providers\LaravelUseCasesServiceProvider;
use Ylsalame\LaravelUseCases\Tests\Concerns\WritesUseCaseFixtures;

/**
 * The end-to-end pipeline: route → FormRequest → UseCase → Resource, with the controller
 * inferring all three from its own class name.
 */
class UseCasePipelineTest extends TestCase
{
    use WritesUseCaseFixtures;

    protected function getPackageProviders($app): array
    {
        return [LaravelUseCasesServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('laravel-use-cases.require_test_class', false);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/dummy', [\App\Http\Controllers\DummyController::class, 'index']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeFixture('app/Http/Controllers/DummyController.php', <<<'CODE'
            <?php
            namespace App\Http\Controllers;
            use Ylsalame\LaravelUseCases\Http\Controllers\BaseController;
            class DummyController extends BaseController {}
            CODE);

        $this->writeFixture('app/Http/Requests/Dummy/DummyIndexRequest.php', <<<'CODE'
            <?php
            namespace App\Http\Requests\Dummy;
            use Illuminate\Foundation\Http\FormRequest;
            class DummyIndexRequest extends FormRequest
            {
                public function authorize(): bool { return true; }
                public function rules(): array { return ['name' => ['required', 'string']]; }
            }
            CODE);

        $this->writeFixture('app/UseCases/Dummy/DummyIndexUseCase.php', <<<'CODE'
            <?php
            namespace App\UseCases\Dummy;
            use Ylsalame\LaravelUseCases\BaseUseCase;
            class DummyIndexUseCase extends BaseUseCase
            {
                protected function execute(array $data)
                {
                    return ['greeting' => 'Hello '.$data['name']];
                }
            }
            CODE);

        $this->writeFixture('app/Http/Resources/Dummy/DummyIndexResource.php', <<<'CODE'
            <?php
            namespace App\Http\Resources\Dummy;
            use Illuminate\Http\Resources\Json\JsonResource;
            class DummyIndexResource extends JsonResource
            {
                public function toArray($request): array { return $this->resource; }
            }
            CODE);
    }

    protected function tearDown(): void
    {
        $this->removeWrittenFixtures();

        parent::tearDown();
    }

    public function test_use_case_pipeline_executes_and_transforms_response(): void
    {
        $response = $this->getJson('/dummy?name=John');

        $response->assertStatus(200);
        $response->assertJson(['greeting' => 'Hello John']);
    }

    public function test_the_form_request_rules_are_enforced(): void
    {
        $this->getJson('/dummy')->assertStatus(422);
    }
}
