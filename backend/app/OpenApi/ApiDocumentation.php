<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Klicklocal Scheduler API',
    description: 'Social media scheduling SaaS API. Authenticate with Bearer token (Laravel Sanctum). Workspace-scoped routes accept `X-Workspace-Id` header or `workspace_id` query/body.',
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
        new OA\Property(property: 'onboarding_step', type: 'string', nullable: true),
        new OA\Property(property: 'onboarding_data', type: 'object', nullable: true),
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
        new OA\Property(property: 'onboarding_step', type: 'integer', nullable: true),
        new OA\Property(property: 'onboarding_completed', type: 'boolean', nullable: true),
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
        new OA\Property(property: 'media_id', type: 'integer', nullable: true),
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: ['draft', 'scheduled', 'processing', 'published', 'failed'],
        ),
        new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'AiGeneration',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'workspace_id', type: 'integer'),
        new OA\Property(property: 'user_id', type: 'integer'),
        new OA\Property(property: 'media_id', type: 'integer', nullable: true),
        new OA\Property(property: 'prompt', type: 'string', nullable: true),
        new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'facebook', 'tiktok', 'linkedin']),
        new OA\Property(property: 'content_type', type: 'string', enum: ['post', 'reel', 'story', 'video']),
        new OA\Property(property: 'seo_focus', type: 'string', nullable: true),
        new OA\Property(property: 'caption', type: 'string', nullable: true),
        new OA\Property(property: 'story_text', type: 'string', nullable: true),
        new OA\Property(property: 'hashtags', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'call_to_action', type: 'string', nullable: true),
        new OA\Property(property: 'model', type: 'string', nullable: true),
        new OA\Property(property: 'tokens_used', type: 'integer', nullable: true),
        new OA\Property(property: 'generated_image_url', type: 'string', nullable: true),
        new OA\Property(property: 'image_model', type: 'string', nullable: true),
        new OA\Property(property: 'image_revised_prompt', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'BusinessProfile',
    properties: [
        new OA\Property(property: 'business_name', type: 'string'),
        new OA\Property(property: 'business_type', type: 'string'),
        new OA\Property(property: 'city', type: 'string', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'tone_of_voice', type: 'string', nullable: true),
        new OA\Property(property: 'products_services', type: 'string', nullable: true),
        new OA\Property(property: 'website', type: 'string', nullable: true),
        new OA\Property(property: 'team_size', type: 'string', nullable: true),
        new OA\Property(property: 'monthly_revenue', type: 'string', nullable: true),
        new OA\Property(property: 'customer_source', type: 'string', nullable: true),
        new OA\Property(property: 'social_media_channels', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'target_audience', type: 'string', nullable: true),
        new OA\Property(property: 'unique_value_proposition', type: 'string', nullable: true),
        new OA\Property(property: 'additional_notes', type: 'string', nullable: true),
        new OA\Property(property: 'primary_goal', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'QuotaAddon',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'workspace_id', type: 'integer'),
        new OA\Property(property: 'feature_key', type: 'string'),
        new OA\Property(property: 'amount', type: 'integer'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'purchased_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'price_paid', type: 'number', format: 'float'),
        new OA\Property(property: 'provider', type: 'string'),
    ],
)]
#[OA\Tag(name: 'Auth', description: 'Registration, login, and session')]
#[OA\Tag(name: 'Onboarding', description: 'User and workspace onboarding')]
#[OA\Tag(name: 'Workspaces', description: 'Workspace CRUD')]
#[OA\Tag(name: 'Business Profile', description: 'Workspace business profile')]
#[OA\Tag(name: 'Posts', description: 'Post CRUD, scheduling, and publishing')]
#[OA\Tag(name: 'Media', description: 'Media uploads and listing')]
#[OA\Tag(name: 'AI', description: 'AI content and image generation')]
#[OA\Tag(name: 'Billing', description: 'Subscriptions, usage, invoices, and quota top-ups')]
#[OA\Tag(name: 'Social Accounts', description: 'Instagram and TikTok OAuth')]
#[OA\Tag(name: 'Admin', description: 'Platform administration (requires platform admin role)')]
// --- Auth ---
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
    path: '/auth/register-email',
    operationId: 'authRegisterEmail',
    tags: ['Auth'],
    summary: 'Start or resume email-only onboarding registration',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Registration started or onboarding resumed'),
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
    summary: 'Get authenticated user and workspace context',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Current user'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ],
)]
#[OA\Get(
    path: '/auth/onboarding',
    operationId: 'authOnboardingStatus',
    tags: ['Onboarding'],
    summary: 'Get user onboarding status',
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Onboarding status'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ],
)]
#[OA\Patch(
    path: '/auth/onboarding',
    operationId: 'authUpdateOnboarding',
    tags: ['Onboarding'],
    summary: 'Save user onboarding progress',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['step'],
            properties: [
                new OA\Property(property: 'step', type: 'string', maxLength: 60),
                new OA\Property(property: 'data', type: 'object', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Progress saved'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
#[OA\Post(
    path: '/auth/onboarding/complete',
    operationId: 'authCompleteOnboarding',
    tags: ['Onboarding'],
    summary: 'Complete onboarding and create workspace',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['password', 'password_confirmation', 'first_name', 'business_name', 'industry'],
            properties: [
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                new OA\Property(property: 'first_name', type: 'string'),
                new OA\Property(property: 'business_name', type: 'string'),
                new OA\Property(property: 'website', type: 'string', nullable: true),
                new OA\Property(property: 'industry', type: 'string'),
                new OA\Property(property: 'team_size', type: 'string', nullable: true),
                new OA\Property(property: 'monthly_revenue', type: 'string', nullable: true),
                new OA\Property(property: 'customer_source', type: 'string', nullable: true),
                new OA\Property(property: 'social_media_channels', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'target_audience', type: 'string', nullable: true),
                new OA\Property(property: 'unique_value_proposition', type: 'string', nullable: true),
                new OA\Property(property: 'additional_notes', type: 'string', nullable: true),
                new OA\Property(property: 'primary_goal', type: 'string', nullable: true),
                new OA\Property(property: 'city', type: 'string', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Onboarding completed'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
#[OA\Post(
    path: '/onboarding/analyze-website',
    operationId: 'onboardingAnalyzeWebsite',
    tags: ['Onboarding'],
    summary: 'Analyze a business website for onboarding (public, rate-limited)',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['website'],
            properties: [
                new OA\Property(property: 'website', type: 'string'),
                new OA\Property(property: 'business_name', type: 'string', nullable: true),
                new OA\Property(property: 'industry', type: 'string', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Website analyzed'),
        new OA\Response(response: 422, description: 'Validation error'),
        new OA\Response(response: 429, description: 'Rate limit exceeded'),
    ],
)]
// --- Workspaces ---
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
#[OA\Patch(
    path: '/workspaces/{workspace}/onboarding',
    operationId: 'workspaceOnboardingUpdate',
    tags: ['Onboarding'],
    summary: 'Update workspace onboarding step',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'step', type: 'integer', minimum: 1, maximum: 4, nullable: true),
                new OA\Property(property: 'completed', type: 'boolean', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Onboarding updated'),
        new OA\Response(response: 403, description: 'Forbidden'),
    ],
)]
// --- Business Profile ---
#[OA\Get(
    path: '/workspaces/{workspace}/business-profile',
    operationId: 'businessProfileShow',
    tags: ['Business Profile'],
    summary: 'Get workspace business profile',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Business profile'),
        new OA\Response(response: 403, description: 'Forbidden'),
    ],
)]
#[OA\Put(
    path: '/workspaces/{workspace}/business-profile',
    operationId: 'businessProfileUpdate',
    tags: ['Business Profile'],
    summary: 'Create or update workspace business profile',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['business_name', 'business_type'],
            properties: [
                new OA\Property(property: 'business_name', type: 'string'),
                new OA\Property(property: 'business_type', type: 'string'),
                new OA\Property(property: 'city', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'tone_of_voice', type: 'string', nullable: true),
                new OA\Property(property: 'products_services', type: 'string', nullable: true),
                new OA\Property(property: 'website', type: 'string', nullable: true),
                new OA\Property(property: 'team_size', type: 'string', nullable: true),
                new OA\Property(property: 'monthly_revenue', type: 'string', nullable: true),
                new OA\Property(property: 'customer_source', type: 'string', nullable: true),
                new OA\Property(property: 'social_media_channels', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                new OA\Property(property: 'target_audience', type: 'string', nullable: true),
                new OA\Property(property: 'unique_value_proposition', type: 'string', nullable: true),
                new OA\Property(property: 'additional_notes', type: 'string', nullable: true),
                new OA\Property(property: 'primary_goal', type: 'string', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Business profile saved'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
// --- AI ---
#[OA\Post(
    path: '/ai/generate',
    operationId: 'aiGenerate',
    tags: ['AI'],
    summary: 'Generate AI social content',
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader')],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['workspace_id'],
            properties: [
                new OA\Property(property: 'workspace_id', type: 'integer'),
                new OA\Property(property: 'media_id', type: 'integer', nullable: true),
                new OA\Property(property: 'prompt', type: 'string', maxLength: 1000, nullable: true),
                new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'facebook', 'tiktok', 'linkedin'], nullable: true),
                new OA\Property(property: 'content_type', type: 'string', enum: ['post', 'reel', 'story', 'video'], nullable: true),
                new OA\Property(property: 'language', type: 'string', enum: ['de', 'en'], nullable: true),
                new OA\Property(property: 'seo_focus', type: 'string', maxLength: 100, nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Content generated'),
        new OA\Response(response: 402, description: 'Quota exceeded or subscription required'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
#[OA\Post(
    path: '/ai/generate-image',
    operationId: 'aiGenerateImage',
    tags: ['AI'],
    summary: 'Generate AI image for social content',
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader')],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['workspace_id'],
            properties: [
                new OA\Property(property: 'workspace_id', type: 'integer'),
                new OA\Property(property: 'prompt', type: 'string', maxLength: 500, nullable: true),
                new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'facebook', 'tiktok', 'linkedin'], nullable: true),
                new OA\Property(property: 'content_type', type: 'string', enum: ['post', 'reel', 'story', 'video'], nullable: true),
                new OA\Property(property: 'generation_id', type: 'integer', nullable: true, description: 'Link image to an existing AI generation'),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Image generated'),
        new OA\Response(response: 402, description: 'Quota exceeded or subscription required'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
#[OA\Get(
    path: '/ai/generations',
    operationId: 'aiGenerationsIndex',
    tags: ['AI'],
    summary: 'List AI generation history for a workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Generation history'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
// --- Posts ---
#[OA\Get(
    path: '/posts',
    operationId: 'postsIndex',
    tags: ['Posts'],
    summary: 'List posts in a workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
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
    parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader')],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['workspace_id'],
            properties: [
                new OA\Property(property: 'workspace_id', type: 'integer'),
                new OA\Property(property: 'title', type: 'string', nullable: true),
                new OA\Property(property: 'content', type: 'string', nullable: true),
                new OA\Property(property: 'media_id', type: 'integer', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Created'),
        new OA\Response(response: 402, description: 'Subscription required'),
    ],
)]
#[OA\Post(
    path: '/posts/quick-publish',
    operationId: 'postsQuickPublish',
    tags: ['Posts'],
    summary: 'Create and immediately publish a post to a connected social account',
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader')],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['platform', 'content'],
            properties: [
                new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'tiktok']),
                new OA\Property(property: 'content', type: 'string'),
                new OA\Property(property: 'media_id', type: 'integer', nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 202, description: 'Queued for publishing'),
        new OA\Response(response: 402, description: 'Quota exceeded'),
        new OA\Response(response: 422, description: 'No connected account or validation error'),
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
                new OA\Property(property: 'media_id', type: 'integer', nullable: true),
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
                new OA\Property(property: 'social_account_ids', type: 'array', items: new OA\Items(type: 'integer')),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Scheduled'),
        new OA\Response(response: 402, description: 'Quota exceeded'),
    ],
)]
#[OA\Post(
    path: '/posts/{post}/publish',
    operationId: 'postsPublish',
    tags: ['Posts'],
    summary: 'Publish post immediately',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'social_account_ids', type: 'array', items: new OA\Items(type: 'integer')),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Queued for publishing'),
    ],
)]
// --- Media ---
#[OA\Get(
    path: '/media',
    operationId: 'mediaIndex',
    tags: ['Media'],
    summary: 'List media in a workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Media list'),
    ],
)]
#[OA\Post(
    path: '/media/upload',
    operationId: 'mediaUpload',
    tags: ['Media'],
    summary: 'Upload image or video',
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader')],
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
        new OA\Response(response: 402, description: 'Quota exceeded'),
    ],
)]
// --- Billing ---
#[OA\Get(
    path: '/billing',
    operationId: 'billingIndex',
    tags: ['Billing'],
    summary: 'Billing overview for active workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Billing overview'),
    ],
)]
#[OA\Get(
    path: '/transactions',
    operationId: 'billingTransactions',
    tags: ['Billing'],
    summary: 'List transactions for active workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Transaction list'),
    ],
)]
#[OA\Get(
    path: '/subscription',
    operationId: 'subscriptionShow',
    tags: ['Billing'],
    summary: 'Get active subscription for workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Subscription details'),
    ],
)]
#[OA\Post(
    path: '/subscription',
    operationId: 'subscriptionSubscribe',
    tags: ['Billing'],
    summary: 'Subscribe workspace to a plan',
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader')],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['plan_id'],
            properties: [
                new OA\Property(property: 'plan_id', type: 'integer'),
                new OA\Property(property: 'billing_cycle', type: 'string', enum: ['monthly', 'yearly'], nullable: true),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Subscription started'),
        new OA\Response(response: 422, description: 'Validation error'),
    ],
)]
#[OA\Post(
    path: '/subscription/cancel',
    operationId: 'subscriptionCancel',
    tags: ['Billing'],
    summary: 'Cancel active workspace subscription',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Subscription cancelled'),
        new OA\Response(response: 404, description: 'No active subscription'),
    ],
)]
#[OA\Get(
    path: '/usage',
    operationId: 'usageIndex',
    tags: ['Billing'],
    summary: 'Feature usage for active workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Usage summary'),
    ],
)]
#[OA\Get(
    path: '/invoices',
    operationId: 'invoicesIndex',
    tags: ['Billing'],
    summary: 'List invoices for active workspace',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader'),
        new OA\Parameter(name: 'workspace_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Invoice list'),
    ],
)]
#[OA\Get(
    path: '/quota/packages',
    operationId: 'quotaPackages',
    tags: ['Billing'],
    summary: 'List available quota top-up packages',
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Package list'),
    ],
)]
#[OA\Post(
    path: '/quota/topup',
    operationId: 'quotaTopup',
    tags: ['Billing'],
    summary: 'Purchase a quota top-up package',
    security: [['sanctum' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceIdHeader')],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['workspace_id', 'package_key'],
            properties: [
                new OA\Property(property: 'workspace_id', type: 'integer'),
                new OA\Property(property: 'package_key', type: 'string', enum: ['ai_monthly_tokens', 'scheduled_posts_monthly']),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Top-up purchased'),
        new OA\Response(response: 402, description: 'Subscription required'),
    ],
)]
// --- Social Accounts ---
#[OA\Get(
    path: '/social-accounts/instagram/connect',
    operationId: 'instagramConnect',
    tags: ['Social Accounts'],
    summary: 'Start Instagram Business Login',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Authorization URL'),
        new OA\Response(response: 503, description: 'Instagram connection disabled'),
    ],
)]
#[OA\Get(
    path: '/social-accounts/instagram/callback',
    operationId: 'instagramCallback',
    tags: ['Social Accounts'],
    summary: 'Instagram OAuth callback (redirect)',
    parameters: [
        new OA\Parameter(name: 'code', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'state', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'error', in: 'query', schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: 302, description: 'Redirect to frontend'),
    ],
)]
#[OA\Post(
    path: '/social-accounts/instagram/disconnect',
    operationId: 'instagramDisconnect',
    tags: ['Social Accounts'],
    summary: 'Disconnect Instagram account',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Disconnected'),
    ],
)]
#[OA\Get(
    path: '/social-accounts/instagram/status',
    operationId: 'instagramStatus',
    tags: ['Social Accounts'],
    summary: 'Instagram connection status',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Connection status'),
    ],
)]
#[OA\Get(
    path: '/social-accounts/tiktok/connect',
    operationId: 'tiktokConnect',
    tags: ['Social Accounts'],
    summary: 'Start TikTok Login',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Authorization URL'),
        new OA\Response(response: 503, description: 'TikTok connection disabled'),
    ],
)]
#[OA\Get(
    path: '/social-accounts/tiktok/callback',
    operationId: 'tiktokCallback',
    tags: ['Social Accounts'],
    summary: 'TikTok OAuth callback (redirect)',
    parameters: [
        new OA\Parameter(name: 'code', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'state', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'error', in: 'query', schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: 302, description: 'Redirect to frontend'),
    ],
)]
#[OA\Post(
    path: '/social-accounts/tiktok/disconnect',
    operationId: 'tiktokDisconnect',
    tags: ['Social Accounts'],
    summary: 'Disconnect TikTok account',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Disconnected'),
    ],
)]
#[OA\Get(
    path: '/social-accounts/tiktok/status',
    operationId: 'tiktokStatus',
    tags: ['Social Accounts'],
    summary: 'TikTok connection status',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Connection status'),
    ],
)]
// --- Admin ---
#[OA\Get(
    path: '/admin/users',
    operationId: 'adminUsersIndex',
    tags: ['Admin'],
    summary: 'List platform users',
    security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'User list')],
)]
#[OA\Get(
    path: '/admin/users/{user}',
    operationId: 'adminUsersShow',
    tags: ['Admin'],
    summary: 'Get platform user',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'User details')],
)]
#[OA\Put(
    path: '/admin/users/{user}/roles',
    operationId: 'adminUsersUpdateRoles',
    tags: ['Admin'],
    summary: 'Update platform roles for a user',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['roles'],
            properties: [
                new OA\Property(
                    property: 'roles',
                    type: 'array',
                    items: new OA\Items(type: 'string', enum: ['super_admin', 'admin', 'support']),
                ),
            ],
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Roles updated')],
)]
#[OA\Get(
    path: '/admin/plans/feature-keys',
    operationId: 'adminPlansFeatureKeys',
    tags: ['Admin'],
    summary: 'List available plan feature keys',
    security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'Feature keys')],
)]
#[OA\Get(
    path: '/admin/plans',
    operationId: 'adminPlansIndex',
    tags: ['Admin'],
    summary: 'List subscription plans',
    security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'Plan list')],
)]
#[OA\Post(
    path: '/admin/plans',
    operationId: 'adminPlansStore',
    tags: ['Admin'],
    summary: 'Create subscription plan',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['slug', 'name', 'monthly_price'],
            properties: [
                new OA\Property(property: 'slug', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'monthly_price', type: 'number', format: 'float'),
                new OA\Property(property: 'yearly_price', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'trial_days', type: 'integer'),
                new OA\Property(property: 'is_active', type: 'boolean'),
                new OA\Property(property: 'sort_order', type: 'integer'),
                new OA\Property(property: 'features', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
            ],
        ),
    ),
    responses: [new OA\Response(response: 201, description: 'Plan created')],
)]
#[OA\Get(
    path: '/admin/plans/{plan}',
    operationId: 'adminPlansShow',
    tags: ['Admin'],
    summary: 'Get subscription plan',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'plan', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Plan details')],
)]
#[OA\Put(
    path: '/admin/plans/{plan}',
    operationId: 'adminPlansUpdate',
    tags: ['Admin'],
    summary: 'Update subscription plan',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'plan', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'slug', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'monthly_price', type: 'number', format: 'float'),
                new OA\Property(property: 'yearly_price', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'trial_days', type: 'integer'),
                new OA\Property(property: 'is_active', type: 'boolean'),
                new OA\Property(property: 'sort_order', type: 'integer'),
                new OA\Property(property: 'features', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
            ],
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Plan updated')],
)]
#[OA\Delete(
    path: '/admin/plans/{plan}',
    operationId: 'adminPlansDestroy',
    tags: ['Admin'],
    summary: 'Delete subscription plan',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'plan', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Plan deleted')],
)]
#[OA\Get(
    path: '/admin/workspaces',
    operationId: 'adminWorkspacesIndex',
    tags: ['Admin'],
    summary: 'List all workspaces',
    security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'Workspace list')],
)]
#[OA\Get(
    path: '/admin/subscriptions',
    operationId: 'adminSubscriptionsIndex',
    tags: ['Admin'],
    summary: 'List all subscriptions',
    security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'Subscription list')],
)]
#[OA\Post(
    path: '/admin/subscriptions',
    operationId: 'adminSubscriptionsStore',
    tags: ['Admin'],
    summary: 'Assign subscription to workspace',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['workspace_id', 'plan_id'],
            properties: [
                new OA\Property(property: 'workspace_id', type: 'integer'),
                new OA\Property(property: 'plan_id', type: 'integer'),
                new OA\Property(property: 'billing_cycle', type: 'string', enum: ['monthly', 'yearly'], nullable: true),
            ],
        ),
    ),
    responses: [new OA\Response(response: 201, description: 'Subscription assigned')],
)]
#[OA\Post(
    path: '/admin/subscriptions/demo',
    operationId: 'adminSubscriptionsGrantDemo',
    tags: ['Admin'],
    summary: 'Grant demo subscription period to workspace',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['workspace_id', 'days'],
            properties: [
                new OA\Property(property: 'workspace_id', type: 'integer'),
                new OA\Property(property: 'days', type: 'integer', minimum: 1, maximum: 365),
            ],
        ),
    ),
    responses: [new OA\Response(response: 201, description: 'Demo period granted')],
)]
#[OA\Delete(
    path: '/admin/subscriptions/{subscription}',
    operationId: 'adminSubscriptionsDestroy',
    tags: ['Admin'],
    summary: 'Cancel a subscription',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'subscription', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Subscription cancelled')],
)]
#[OA\Get(
    path: '/admin/transactions',
    operationId: 'adminTransactionsIndex',
    tags: ['Admin'],
    summary: 'List all platform transactions',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 100)),
    ],
    responses: [new OA\Response(response: 200, description: 'Transaction list')],
)]
#[OA\Get(
    path: '/admin/coupons',
    operationId: 'adminCouponsIndex',
    tags: ['Admin'],
    summary: 'List coupons',
    security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'Coupon list')],
)]
#[OA\Post(
    path: '/admin/coupons',
    operationId: 'adminCouponsStore',
    tags: ['Admin'],
    summary: 'Create coupon',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['code', 'name', 'type', 'value'],
            properties: [
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'type', type: 'string', enum: ['percent', 'fixed']),
                new OA\Property(property: 'value', type: 'number', format: 'float'),
                new OA\Property(property: 'max_redemptions', type: 'integer', nullable: true),
                new OA\Property(property: 'valid_from', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'valid_until', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
        ),
    ),
    responses: [new OA\Response(response: 201, description: 'Coupon created')],
)]
#[OA\Put(
    path: '/admin/coupons/{coupon}',
    operationId: 'adminCouponsUpdate',
    tags: ['Admin'],
    summary: 'Update coupon',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'coupon', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'type', type: 'string', enum: ['percent', 'fixed']),
                new OA\Property(property: 'value', type: 'number', format: 'float'),
                new OA\Property(property: 'max_redemptions', type: 'integer', nullable: true),
                new OA\Property(property: 'valid_from', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'valid_until', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Coupon updated')],
)]
#[OA\Get(
    path: '/admin/settings',
    operationId: 'adminSettingsIndex',
    tags: ['Admin'],
    summary: 'Get platform settings',
    security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'Platform settings')],
)]
#[OA\Put(
    path: '/admin/settings',
    operationId: 'adminSettingsUpdate',
    tags: ['Admin'],
    summary: 'Update platform settings',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'app_name', type: 'string'),
                new OA\Property(property: 'support_email', type: 'string', format: 'email'),
                new OA\Property(property: 'default_timezone', type: 'string'),
                new OA\Property(property: 'maintenance_mode', type: 'boolean'),
                new OA\Property(property: 'trial_days', type: 'integer', minimum: 0, maximum: 90),
            ],
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Settings updated')],
)]
#[OA\Get(
    path: '/admin/ai-prompts',
    operationId: 'adminAiPromptsIndex',
    tags: ['Admin'],
    summary: 'List AI prompt templates',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', enum: ['caption', 'content', 'hashtag', 'reply', 'scheduling', 'brand_voice'])),
        new OA\Parameter(name: 'active_only', in: 'query', schema: new OA\Schema(type: 'boolean')),
    ],
    responses: [new OA\Response(response: 200, description: 'Prompt list')],
)]
#[OA\Post(
    path: '/admin/ai-prompts',
    operationId: 'adminAiPromptsStore',
    tags: ['Admin'],
    summary: 'Create AI prompt template',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['key', 'name', 'category', 'template'],
            properties: [
                new OA\Property(property: 'key', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'category', type: 'string', enum: ['caption', 'content', 'hashtag', 'reply', 'scheduling', 'brand_voice']),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'template', type: 'string'),
                new OA\Property(property: 'variables', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
        ),
    ),
    responses: [new OA\Response(response: 201, description: 'Prompt created')],
)]
#[OA\Get(
    path: '/admin/ai-prompts/{aiPromptTemplate}',
    operationId: 'adminAiPromptsShow',
    tags: ['Admin'],
    summary: 'Get AI prompt template',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'aiPromptTemplate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Prompt details')],
)]
#[OA\Put(
    path: '/admin/ai-prompts/{aiPromptTemplate}',
    operationId: 'adminAiPromptsUpdate',
    tags: ['Admin'],
    summary: 'Update AI prompt template',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'aiPromptTemplate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'category', type: 'string', enum: ['caption', 'content', 'hashtag', 'reply', 'scheduling', 'brand_voice']),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'template', type: 'string'),
                new OA\Property(property: 'variables', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Prompt updated')],
)]
#[OA\Patch(
    path: '/admin/ai-prompts/{aiPromptTemplate}/active',
    operationId: 'adminAiPromptsSetActive',
    tags: ['Admin'],
    summary: 'Activate or deactivate AI prompt template',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'aiPromptTemplate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['is_active'],
            properties: [
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Prompt status updated')],
)]
#[OA\Get(
    path: '/admin/usage',
    operationId: 'adminUsageIndex',
    tags: ['Admin'],
    summary: 'Platform usage analytics',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 500)),
    ],
    responses: [new OA\Response(response: 200, description: 'Usage analytics')],
)]
#[OA\Get(
    path: '/admin/quota-addons',
    operationId: 'adminQuotaAddonsIndex',
    tags: ['Admin'],
    summary: 'List quota add-ons',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'workspace_id', in: 'query', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Quota add-on list')],
)]
#[OA\Post(
    path: '/admin/quota-addons',
    operationId: 'adminQuotaAddonsStore',
    tags: ['Admin'],
    summary: 'Manually assign quota add-on to workspace',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['workspace_id', 'feature_key', 'amount', 'price_paid'],
            properties: [
                new OA\Property(property: 'workspace_id', type: 'integer'),
                new OA\Property(
                    property: 'feature_key',
                    type: 'string',
                    enum: [
                        'max_workspaces', 'max_social_accounts', 'max_team_members', 'ai_generation',
                        'ai_monthly_tokens', 'analytics_enabled', 'video_generation', 'storage_limit_mb',
                        'scheduled_posts_monthly', 'media_uploads_monthly', 'api_calls_monthly',
                    ],
                ),
                new OA\Property(property: 'amount', type: 'integer', minimum: 1),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'price_paid', type: 'number', format: 'float', minimum: 0),
            ],
        ),
    ),
    responses: [new OA\Response(response: 201, description: 'Quota add-on assigned')],
)]
#[OA\Get(
    path: '/admin/providers',
    operationId: 'adminProvidersIndex',
    tags: ['Admin'],
    summary: 'List social provider settings',
    security: [['sanctum' => []]],
    responses: [new OA\Response(response: 200, description: 'Provider list')],
)]
#[OA\Put(
    path: '/admin/providers/{provider}',
    operationId: 'adminProvidersUpdate',
    tags: ['Admin'],
    summary: 'Update social provider settings',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'enabled', type: 'boolean'),
                new OA\Property(property: 'app_id', type: 'string', nullable: true),
                new OA\Property(property: 'callback_url', type: 'string', format: 'uri', nullable: true),
                new OA\Property(property: 'api_version', type: 'string', nullable: true),
                new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
            ],
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Provider updated')],
)]
#[OA\Put(
    path: '/admin/providers/instagram',
    operationId: 'adminProvidersUpdateInstagram',
    tags: ['Admin'],
    summary: 'Update Instagram provider settings',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'enabled', type: 'boolean'),
                new OA\Property(property: 'app_id', type: 'string', nullable: true),
                new OA\Property(property: 'callback_url', type: 'string', format: 'uri', nullable: true),
                new OA\Property(property: 'api_version', type: 'string', nullable: true),
                new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
            ],
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Instagram provider updated')],
)]
class ApiDocumentation
{
}
