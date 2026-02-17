# App\UseCases Rules

## General Principles

UseCases are single-use, business-logic oriented and self-contained functionality holders. They are intended to provide the **lowest denominator** of a business logic to make system maintenance easy, simple and straight-forward

## Request lifecycle

Using Laravel you would normally have an endpoint that when hit, triggers a Controller that then distributes the workload of validation, UI management, bussiness rules, error handling and response structure.

What this library aims to do is to simplify this lifecycle by abstracting the most common elements of it and delegating them to their responsible classes albeit automatically. It aims to make things as DRY and simple as possible.

## Folder+File Structure

UseCases are stored in `/app/UseCases/` under folders that group their target/entity
eg.: `Webhooks/`, `Events/`, `Project/`, `Model/`, etc.

A UseCase file/class should be named after their object+action

- File naming: `{object}{action}UseCase.php` (e.g., `ProjectCreateUseCase.php`)
- Class naming: `{object}{action}UseCase` (e.g., `ProjectCreateUseCase`)

The naming convention plays a part into the automation of the file structure and associated responsabilities.

So we have:
- Endpoint management (Route)
- Entry point (Controller)
- Validation (Form Request)
- Business Rules (Use Case)
- Output formatting (Resource)

This should translate into:
- /routes/api.php
- /app/Http/Controllers/ExampleController.php
- /app/Requests/Example/ExamplePingRequest.php
- /app/UseCases/Example/ExamplePingUseCase.php
- /app/Resources/Example/ExamplePingResource.php


## Clean code and DRY implementation

DRY Implementation allows us to avoid having to rewrite multiple parts of our code. If you adhere to the naming conventions the non-bussiness logic is taken care of for you.

In the Route you will do the same thing you always did before. Asign a controller+method to an endpoint.

In a UseCase you will:
- extend the BaseUseCase from this package
- not have to use transactions since they are built in
- not have to validate the data since the Form Request is triggered before the Use Case is
- not have to call the Resource to format the response since anything you return from the Use Case is piped into the Resource already

In a Controller you will:
- extend the BaseController from this package
- not have to add any code to it
-- the Use Case will be called based on the naming conventions
-- the data sent from the endpoint URL will be appeneded to the data sent to the Use Case
-- the Form Request will be triggered passing the data collated for the request


## Example Code

With the premise that I want to fetch the list of Users from an application:

The /routes/api.php routes file:
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::prefix('/user')
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/', 'index')->name('user.index');
    });
```

The /app/Http/Controllers/UserController.php file:
```php
<?php

namespace App\Http\Controllers;

use Ylsalame\LaravelUseCases\BaseController;

class ExampleController extends BaseController
{
    //empty
}
```

The /app/UseCases/User/UserIndexUseCase.php file:
```php
<?php

namespace App\UseCases\User;

use App\Models\Project;
use Ylsalame\LaravelUseCases\BaseUseCase;

class UserIndexUseCase extends BaseUseCase
{
    protected function execute(array $data): Project
    {
        $users = Users::get();

        return $users;
    }
}
```

The /app/Requests/User/UserIndexRequest.php file:
```php
<?php

namespace App\Http\Requests\User;

use App\Http\Requests\AbstractRequest;

class UserIndexRequest extends AbstractRequest
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }
}
```






- When a UseCase already contains logic that another UseCase needs to function properly, then a UseCase can call another one and use its raw output for its logic. eg.: UseCase 1 inserts a single Project - UseCase 2 needs to insert a list of Projects from a CSV - UseCase 2 processes the file but uses UseCase 1 to trigger the inserting of Projects instead of duplicating code and logic.

## File Structure
- UseCases are stored in `/app/UseCases/` under folders that group their target/entity
- Examples: `Webhooks/`, `Events/`, `Project/`, `Model/`, etc.
- File naming: `{object}{action}UseCase.php` (e.g., `ProjectCreateUseCase.php`)
- Class naming: `{object}{action}UseCase` (e.g., `ProjectCreateUseCase`)

## Method Implementation
- UseCases should have only one method: `execute(array $data)`
- The `execute` method should **NOT** have a doc block as the UseCase name should be self-explanatory
- UseCases should **NOT** receive single named parameters - always receive only one parameter: `array $data`
- Any parameters needed in the UseCase should be included in the `$data` array
- Access data using array keys: `$data['project_uuid']`, `$data['name']`, etc.
- Data is validated before the UseCase is triggered via FormRequest inferrence

## Naming Conventions
- **Preset methods** (use standard names): `index`, `show`, `create`, `update`, `destroy`, `dependencies`, `reorder`
- **Custom methods** (use verb+object pattern): `getAdditionalRecords`, `deleteAssociatedData`, `moveLines`

## Model Access Patterns
- Use `Project::findByUuid($uuid)` for models that don't require scoping (do not have a trait called HasFrom{Object}Scope)
- Always handle the case where models might not exist and raise errors when needed
- Do **NOT** use try/catch blocks since this is already done in the logic on ExtendableController

## Call workflow
- When a Use Case is called, it uses the `{UseCase}->handle($data)` method. This method triggers the validation that the UseCase has a Form Request, Feature Test, and Resource. If one of these files do not exist and are not correctly coded then the Use Case will trigger a 500 error and log the issue
- If all files are correctly verified, the code will:
-- collate all URL, Query and Route parameters and retain them
-- execute the Form Request with the collated data and retain the validated data returned from it
-- call the Use Case using the validated data from the Form Request and retain the output returned from it
-- call the Resource using the Use Case output and retain the output returned from it
-- build a Response using the Resource output and return it in the endpoint

## Best Practices
- Keep UseCases focused on a single responsibility
- Use dependency injection for other UseCases when needed
- Return the appropriate data type (Model, Collection, array, void)
