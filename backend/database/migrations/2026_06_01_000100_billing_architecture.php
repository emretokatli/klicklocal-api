<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'monthly_price')) {
                $table->decimal('monthly_price', 10, 2)->default(0)->after('description');
            }
            if (! Schema::hasColumn('plans', 'yearly_price')) {
                $table->decimal('yearly_price', 10, 2)->default(0)->after('monthly_price');
            }
            if (! Schema::hasColumn('plans', 'trial_days')) {
                $table->unsignedSmallInteger('trial_days')->default(14)->after('yearly_price');
            }
        });

        if (Schema::hasColumn('plans', 'price_monthly_cents')) {
            foreach (DB::table('plans')->orderBy('id')->get() as $plan) {
                DB::table('plans')->where('id', $plan->id)->update([
                    'monthly_price' => ($plan->price_monthly_cents ?? 0) / 100,
                    'yearly_price' => ($plan->price_yearly_cents ?? 0) / 100,
                ]);
            }
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn(['price_monthly_cents', 'price_yearly_cents', 'limits', 'features']);
            });
        }

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 64);
            $table->string('feature_value', 255);
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });

        if (Schema::hasColumn('subscriptions', 'user_id')) {
            foreach (DB::table('subscriptions')->orderBy('id')->get() as $sub) {
                $workspaceId = DB::table('workspaces')
                    ->where('owner_id', $sub->user_id)
                    ->value('id')
                    ?? DB::table('workspace_members')->where('user_id', $sub->user_id)->value('workspace_id');

                if ($workspaceId !== null) {
                    DB::table('subscriptions')->where('id', $sub->id)->update(['workspace_id' => $workspaceId]);
                }
            }

            Schema::table('subscriptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscriptions', 'workspace_id')) {
                    $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
                }
                if (! Schema::hasColumn('subscriptions', 'provider')) {
                    $table->string('provider', 32)->default('manual');
                }
                if (! Schema::hasColumn('subscriptions', 'renewal_at')) {
                    $table->timestamp('renewal_at')->nullable();
                }
                if (! Schema::hasColumn('subscriptions', 'provider_customer_id')) {
                    $table->string('provider_customer_id')->nullable();
                }
                if (! Schema::hasColumn('subscriptions', 'provider_subscription_id')) {
                    $table->string('provider_subscription_id')->nullable()->index();
                }
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id', 'status']);
                $table->dropColumn('user_id');
            });

            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->index(['workspace_id', 'status']);
            });
        }

        if (Schema::hasColumn('subscriptions', 'external_id')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->dropColumn('external_id');
            });
        }

        Schema::create('subscription_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 64);
            $table->unsignedBigInteger('used_value')->default(0);
            $table->timestamp('reset_at');
            $table->timestamps();

            $table->unique(['workspace_id', 'feature_key', 'reset_at'], 'subscription_usage_period_unique');
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('stripe');
            $table->string('provider_transaction_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 32)->default('open');
            $table->string('pdf_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 16);
            $table->decimal('value', 10, 2);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'workspace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('subscription_usage');
        Schema::dropIfExists('plan_features');

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('subscriptions', 'workspace_id')) {
                $table->dropForeign(['workspace_id']);
                $table->dropColumn([
                    'workspace_id',
                    'provider',
                    'renewal_at',
                    'provider_customer_id',
                    'provider_subscription_id',
                ]);
            }
        });
    }
};
