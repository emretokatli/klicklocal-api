<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Parameter(
    parameter: 'WorkspaceIdHeader',
    name: 'X-Workspace-Id',
    in: 'header',
    required: false,
    description: 'Active workspace ID (alternative to workspace_id query/body).',
    schema: new OA\Schema(type: 'integer'),
)]
class Parameters
{
}
