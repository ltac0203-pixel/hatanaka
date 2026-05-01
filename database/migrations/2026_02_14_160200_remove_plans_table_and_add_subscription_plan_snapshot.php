<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 契約にプラン情報を閉じ込め、参照整合性の外部依存を減らす。
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('fincode_plan_id', 25)->nullable()->after('user_id');
            $table->string('plan_name')->nullable()->after('fincode_plan_id');
            $table->integer('plan_amount')->nullable()->after('plan_name');
            $table->string('plan_interval', 20)->nullable()->after('plan_amount');
            $table->integer('plan_interval_count')->nullable()->after('plan_interval');
            $table->json('plan_snapshot')->nullable()->after('plan_interval_count');
            $table->index('fincode_plan_id');
        });

        if (Schema::hasTable('plans') && Schema::hasColumn('subscriptions', 'plan_id')) {
            $subscriptions = DB::table('subscriptions')
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->select([
                    'subscriptions.id',
                    'plans.fincode_plan_id',
                    'plans.name',
                    'plans.description',
                    'plans.amount',
                    'plans.interval',
                    'plans.interval_count',
                    'plans.status',
                    'plans.features',
                    'plans.metadata',
                ])
                ->get();

            foreach ($subscriptions as $subscription) {
                DB::table('subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'fincode_plan_id' => $subscription->fincode_plan_id,
                        'plan_name' => $subscription->name,
                        'plan_amount' => $subscription->amount,
                        'plan_interval' => $subscription->interval,
                        'plan_interval_count' => $subscription->interval_count,
                        'plan_snapshot' => json_encode([
                            'fincode_plan_id' => $subscription->fincode_plan_id,
                            'name' => $subscription->name,
                            'description' => $subscription->description,
                            'amount' => (int) $subscription->amount,
                            'interval' => $subscription->interval,
                            'interval_count' => (int) $subscription->interval_count,
                            'status' => $subscription->status,
                            'features' => $this->decodeJson($subscription->features),
                            'metadata' => $this->decodeJson($subscription->metadata),
                            'price_display' => $this->priceDisplay((int) $subscription->amount, (string) $subscription->interval, (int) $subscription->interval_count),
                            'interval_label' => $this->intervalLabel((string) $subscription->interval, (int) $subscription->interval_count),
                        ]),
                    ]);
            }
        }

        if (Schema::hasColumn('subscriptions', 'plan_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
                $table->dropColumn('plan_id');
            });
        }

        Schema::dropIfExists('plans');
    }

    /**
     * 旧プランテーブル依存へ戻せるようスキーマを復元する。
     */
    public function down(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('fincode_plan_id', 25)->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->integer('amount');
                $table->enum('interval', ['monthly', 'yearly', 'weekly', 'daily'])->default('monthly');
                $table->integer('interval_count')->default(1);
                $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
                $table->json('features')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index('slug');
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->dropColumn([
                'fincode_plan_id',
                'plan_name',
                'plan_amount',
                'plan_interval',
                'plan_interval_count',
                'plan_snapshot',
            ]);
        });
    }

    private function decodeJson(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return $value;
    }

    private function intervalLabel(string $interval, int $intervalCount): string
    {
        $labels = [
            'monthly' => '月',
            'yearly' => '年',
            'weekly' => '週',
            'daily' => '日',
        ];

        $label = $labels[$interval] ?? $interval;

        return $intervalCount > 1 ? "{$intervalCount}{$label}" : $label;
    }

    private function priceDisplay(int $amount, string $interval, int $intervalCount): string
    {
        return '¥'.number_format($amount).'/'.$this->intervalLabel($interval, $intervalCount);
    }
};
