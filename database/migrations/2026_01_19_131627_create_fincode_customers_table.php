<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ローカルユーザーと Fincode 顧客を結び付ける保存先を作成する。
     */
    public function up(): void
    {
        Schema::create('fincode_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('fincode_customer_id', 25)->unique()->comment('c_xxx');
            $table->string('name');
            $table->string('email');
            $table->string('phone_cc', 5)->nullable();
            $table->string('phone_no', 20)->nullable();
            $table->string('addr_country', 3)->nullable();
            $table->string('addr_state')->nullable();
            $table->string('addr_city')->nullable();
            $table->string('addr_line_1')->nullable();
            $table->string('addr_line_2')->nullable();
            $table->string('addr_post_code', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fincode_customer_id');
        });
    }

    /**
     * 顧客同期基盤を巻き戻せるようテーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('fincode_customers');
    }
};
