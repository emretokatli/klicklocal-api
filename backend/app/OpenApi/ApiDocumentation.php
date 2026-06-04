<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Klicklocal Scheduler API',
    description: 'Social media scheduling SaaS API. Authenticate with Bearer token (Laravel Sanctum).',
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'API v1 base URL',
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum token',
    description: 'Paste only the token from login/register (e.g. 18|abc...). Do not type "Bearer" — Swagger adds it automatically.',
)]
#[OA\Schema(
    schema: 'ApiSuccessEnvelope',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Success'),
        new OA\Property(property: 'data', type: 'object'),
    ],
)]
#[OA\Schema(
    schema: 'ApiErrorEnvelope',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
        ),
    ],
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'Workspace',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'owner_id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'logo', type: 'string', nullable: true),
        new OA\Property(property: 'timezone', type: 'string', example: 'UTC'),
    ],
)]
#[OA\Schema(
    schema: 'Post',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'workspace_id', type: 'integer'),
        new OA\Property(property: 'user_id', type: 'integer'),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'content', type: 'string', nullable: true),
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: ['draft', 'scheduled', 'processing', 'published', 'failed'],
        ),
        new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
#[OA\Tag(name: 'Auth', description: 'Registration, login, and session')]
#[OA\Tag(name: 'Workspaces', description: 'Workspace CRUD')]
#[OA\Tag(name: 'Posts', description: 'Post CRUD and scheduling')]
#[OA\Tag(name: 'Media', description: 'Media uploads')]
#[OA\Post(
    path: '/auth/register',
    operationId: 'authRegister',
    tags: ['Auth'],
    summary: 'Register a new user',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Registered'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
#[OA\Post(
    path: '/auth/login',
    operationId: 'authLogin',
    tags: ['Auth'],
    summary: 'Login and receive bearer token',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Logged in'),
        new OA\Response(response: 422, description: 'Invalid credentials'),
    ],
)]
#[OA\Post(
    path: '/auth/logout',
    operationId: 'authLogout',
    tags: ['Auth'],
    summary: 'Revoke current token',
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Logged out'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ],
)]
#[OA\Get(
    path: '/auth/me',
    operationId: 'authMe',
    tags: ['Auth'],
    summary: 'Get authenticated user',
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Current user'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ],
)]
#[OA\Get(
    path: '/workspaces',
    operationId: 'workspacesIndex',
    tags: ['Workspaces'],
    summary: 'List workspaces for current user',
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Workspace list'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ],
)]
#[OA\Post(
    path: '/workspaces',
    operationId: 'workspacesStore',
    tags: ['Workspaces'],
    summary: 'Create workspace',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'timezone', type: 'string'),
                new OA\Property(property: 'logo', type: 'string', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Created'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
#[OA\Get(
    path: '/workspaces/{workspace}',
    operationId: 'workspacesShow',
    tags: ['Workspaces'],
    summary: 'Get workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Workspace details'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Not found'),
    ],
)]
#[OA\Put(
    path: '/workspaces/{workspace}',
    operationId: 'workspacesUpdate',
    tags: ['Workspaces'],
    summary: 'Update workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'timezone', type: 'string'),
                new OA\Property(property: 'logo', type: 'string', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Updated'),
        new OA\Response(response: 403, description: 'Forbidden'),
    ],
)]
#[OA\Delete(
    path: '/workspaces/{workspace}',
    operationId: 'workspacesDestroy',
    tags: ['Workspaces'],
    summary: 'Delete workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Deleted'),
        new OA\Response(response: 403, description: 'Forbidden'),
    ],
)]
#[OA\Get(
    path: '/posts',
    operationId: 'postsIndex',
    tags: ['Posts'],
    summary: 'List posts in a workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(
            name: 'workspace_id',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Post list'),
    ],
)]
#[OA\Post(
    path: '/posts',
    operationId: 'postsStore',
    tags: ['Posts'],
    summary: 'Create draft post',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['workspace_id'],
            properties: [
                new OA\Property(property: 'workspace_id', type: 'integer'),
                new OA\Property(property: 'title', type: 'string', nullable: true),
                new OA\Property(property: 'content', type: 'string', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Created'),
    ],
)]
#[OA\Get(
    path: '/posts/{post}',
    operationId: 'postsShow',
    tags: ['Posts'],
    summary: 'Get post',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Post details'),
    ],
)]
#[OA\Put(
    path: '/posts/{post}',
    operationId: 'postsUpdate',
    tags: ['Posts'],
    summary: 'Update post',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string', nullable: true),
                new OA\Property(property: 'content', type: 'string', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Updated'),
    ],
)]
#[OA\Delete(
    path: '/posts/{post}',
    operationId: 'postsDestroy',
    tags: ['Posts'],
    summary: 'Delete post',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Deleted'),
    ],
)]
#[OA\Post(
    path: '/posts/{post}/schedule',
    operationId: 'postsSchedule',
    tags: ['Posts'],
    summary: 'Schedule post for publishing',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['scheduled_at'],
            properties: [
                new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time'),
                new OA\Property(
                    property: 'social_account_ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Scheduled'),
    ],
)]
#[OA\Post(
    path: '/media/upload',
    operationId: 'mediaUpload',
    tags: ['Media'],
    summary: 'Upload image or video',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['workspace_id', 'file'],
                properties: [
                    new OA\Property(property: 'workspace_id', type: 'integer'),
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                ],
            ),
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Uploaded'),
    ],
)]
class ApiDocumentation
{
}
