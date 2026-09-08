<?php

declare(strict_types=1);

namespace Ylsalame\LaravelUseCases\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Ylsalame\LaravelUseCases\Providers\LaravelUseCasesServiceProvider;
use Ylsalame\LaravelUseCases\Tests\Concerns\WritesUseCaseFixtures;

/**
 * A path segment identifies the resource being addressed, so it must win over a body
 * field of the same name.
 *
 * BaseController hands the use case array_merge(body, routeParameters). With that order
 * reversed — as it was — POST /widgets/from-url carrying {"widget_id": "from-body"}
 * acted on "from-body" and the URL was decorative. Every endpoint whose route parameter
 * identifies a record was affected, and anything keyed on the URL (logging, auditing,
 * authorisation) was bypassable by the caller supplying the field in the payload.
 */
class RouteParameterPrecedenceTest extends TestCase
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
        $router->post('/widgets/{widget_id}', [\App\Http\Controllers\WidgetController::class, 'update']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeFixture('app/Http/Controllers/WidgetController.php', <<<'CODE'
            <?php
            namespace App\Http\Controllers;
            use Ylsalame\LaravelUseCases\Http\Controllers\BaseController;
            class WidgetController extends BaseController {}
            CODE);

        $this->writeFixture('app/Http/Requests/Widget/WidgetUpdateRequest.php', <<<'CODE'
            <?php
            namespace App\Http\Requests\Widget;
            use Illuminate\Foundation\Http\FormRequest;
            class WidgetUpdateRequest extends FormRequest
            {
                public function authorize(): bool { return true; }
                public function rules(): array { return ['widget_id' => ['required', 'string']]; }
            }
            CODE);

        $this->writeFixture('app/UseCases/Widget/WidgetUpdateUseCase.php', <<<'CODE'
            <?php
            namespace App\UseCases\Widget;
            use Ylsalame\LaravelUseCases\BaseUseCase;
            class WidgetUpdateUseCase extends BaseUseCase
            {
                protected function execute(array $data)
                {
                    return ['acted_on' => $data['widget_id']];
                }
            }
            CODE);

        $this->writeFixture('app/Http/Resources/Widget/WidgetUpdateResource.php', <<<'CODE'
            <?php
            namespace App\Http\Resources\Widget;
            use Illuminate\Http\Resources\Json\JsonResource;
            class WidgetUpdateResource extends JsonResource
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

    public function test_a_body_field_cannot_override_a_route_parameter(): void
    {
        $response = $this->postJson('/widgets/from-url', ['widget_id' => 'from-body']);

        $response->assertStatus(200);
        $this->assertSame(
            'from-url',
            $response->json('acted_on'),
            'the path segment must identify the resource, not the payload',
        );
    }

    public function test_the_route_parameter_is_still_passed_when_the_body_is_silent(): void
    {
        $response = $this->postJson('/widgets/from-url', []);

        $response->assertStatus(200);
        $this->assertSame('from-url', $response->json('acted_on'));
    }
}
