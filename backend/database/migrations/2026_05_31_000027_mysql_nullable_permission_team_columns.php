<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Intentionally a no-op.
     *
     * `workspace_id` is part of the composite PRIMARY KEY of `model_has_roles`
     * and `model_has_permissions`. MySQL forbids a nullable column inside a
     * PRIMARY KEY (error 1171), so the column stays NOT NULL. Platform-scoped
     * roles use workspace_id = 0 (App\Support\TeamContext::PLATFORM), never NULL.
     */
    public function up(): void
    {
        // no-op
    }

    public function down(): void
    {
        // no-op
    }
};
