<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 継続課金契約を追跡するための基盤テーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('fincode_subscription_id', 25)->unique()->comment('sb_xxx');
            $table->string('fincode_customer_id', 25);
            $table->foreign('fincode_customer_id')->references('fincode_customer_id')->on('fincode_customers')->cascadeOnDelete();
            $table->string('fincode_card_id', 25);
            $table->enum('status', ['active', 'canceled', 'expired', 'unpaid', 'incomplete'])->default('incomplete');
            $table->date('start_date');
            $table->date('stop_date')->nullable();
            $table->date('next_charge_date')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('next_charge_date');
        });
    }

    /**
     * 契約基盤を巻き戻せるようテーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
