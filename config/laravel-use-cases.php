<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Root Namespace
    |--------------------------------------------------------------------------
    |
    | The base namespace of your application. In a typical Laravel app this
    | is "App". All other namespaces below are derived from this.
    |
    */

    'root_namespace' => 'App',

    /*
    |--------------------------------------------------------------------------
    | UseCase, Request and Resource Namespaces
    |--------------------------------------------------------------------------
    |
    | These control where the package looks for UseCases, Form Requests and
    | Resources. You can override them if your app structure differs.
    |
    */

    'use_cases_namespace' => 'App\\UseCases',
    'requests_namespace' => 'App\\Http\\Requests',
    'resources_namespace' => 'App\\Http\\Resources',

    /*
    |--------------------------------------------------------------------------
    | Inference Options
    |--------------------------------------------------------------------------
    |
    | Whether to require the Feature Test class to exist when resolving an
    | endpoint. For most apps this can be false; you can enable it to keep
    | stronger guarantees during development.
    |
    */

    'require_test_class' => false,

    /*
    |--------------------------------------------------------------------------
    | API Error Handler
    |--------------------------------------------------------------------------
    |
    | Configure how UseCase API errors (exceptions from Form Request validation,
    | UseCase execution, etc.) are handled.
    |
    | error_handler_mode: One of 'package', 'custom', or 'wrapper'
    |
    |   - package: Use the package's built-in error handler exclusively.
    |     Returns structured JSON with status, statusCode, errorData, etc.
    |
    |   - custom: Use your own error handler. The handler receives only the
    |     exception and the UseCase data (route params + request input).
    |     Set error_handler_class to your handler's FQCN.
    |
    |   - wrapper: Use the package's error handler to produce the response,
    |     then pass that response to your wrapper. Your wrapper can modify
    |     or wrap the response before returning. Set error_handler_wrapper_class.
    |
    */

    'error_handler_mode' => 'package',

    'error_handler_class' => null,

    'error_handler_wrapper_class' => null,
];
