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
];

