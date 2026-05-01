<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 契約可能プランをローカル管理できる保存先を作成する。
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('fincode_plan_id', 25)->unique()->comment('pl_xxx');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('amount')->comment('価格（税込、円）');
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

    /**
     * 旧プラン管理基盤を巻き戻せるようテーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
