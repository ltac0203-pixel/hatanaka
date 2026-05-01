<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 利用者ごとの決済カードを安全に保持する保存先を作成する。
     */
    public function up(): void
    {
        Schema::create('fincode_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fincode_customer_id', 25);
            $table->foreign('fincode_customer_id')->references('fincode_customer_id')->on('fincode_customers')->cascadeOnDelete();
            $table->string('fincode_card_id', 25)->unique()->comment('cs_xxx');
            $table->string('brand', 20)->comment('Visa, Mastercard等');
            $table->string('last4', 4)->comment('下4桁');
            $table->integer('exp_month')->comment('1-12');
            $table->integer('exp_year')->comment('4桁');
            $table->string('holder_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * カード保存基盤を巻き戻せるようテーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('fincode_cards');
    }
};
