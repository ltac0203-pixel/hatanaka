<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 課金結果と失敗理由を蓄積する履歴テーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('subscription_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fincode_subscription_id', 25);
            $table->string('fincode_payment_id', 25)->nullable()->comment('pay_xxx');
            $table->enum('status', ['success', 'failed', 'pending', 'canceled'])->default('pending');
            $table->integer('amount');
            $table->integer('tax')->nullable();
            $table->date('charged_at_date');
            $table->timestamp('charged_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('fincode_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'charged_at_date']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * 課金履歴基盤を巻き戻せるようテーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_results');
    }
};
