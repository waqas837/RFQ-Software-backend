<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'RFQ Software API Documentation',
            ],
            'routes' => [
                'docs' => 'api/documentation',
                'oauth2_callback' => 'api/oauth2-callback',
                'middleware' => [
                    'api' => [],
                    'asset' => [],
                    'docs' => [],
                    'oauth2_callback' => [],
                ],
                'group' => 'api',
            ],
            'paths' => [
                'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
                'annotations' => [
                    base_path('app'),
                ],
            ],
        ],
    ],
    'defaults' => [
        'routes' => [
            'docs' => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
        ],
        'controller' => \L5Swagger\Http\Controllers\SwaggerController::class,
        'view' => 'l5-swagger::index',
        'middleware' => [],
        'group' => 'api',
        'group_delimiter' => '.',
        'defaults' => [
            'responses' => [
                'examples' => [
                    'application/json' => [
                        'success' => true,
                        'message' => 'Success',
                        'data' => [],
                    ],
                ],
            ],
        ],
    ],
    'security' => [
        'securityDefinitions' => [
            'securitySchemes' => [
                'sanctum' => [
                    'type' => 'http',
                    'description' => 'Enter token in format (Bearer <token>)',
                    'name' => 'Authorization',
                    'in' => 'header',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
        ],
        'security' => [
            [
                'sanctum' => [],
            ],
        ],
    ],
    'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
    'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
    'proxy' => false,
    'additional_config_url' => null,
    'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', 'method'),
    'validator_url' => null,
    'ui' => [
        'title' => 'RFQ Software API',
        'theme' => 'default',
        'display_operation_id' => false,
        'display_request_duration' => true,
        'try_it_out_enabled' => true,
        'supported_submit_methods' => [
            'get',
            'post',
            'put',
            'delete',
            'patch',
        ],
        'validator_url' => null,
    ],
    'constants' => [
        'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000'),
    ],
];
