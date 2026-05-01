<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 顧客削除時に契約とカードの連鎖削除が阻害されないよう外部キーを調整する。
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['fincode_card_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('fincode_card_id')
                ->references('fincode_card_id')
                ->on('fincode_cards')
                ->cascadeOnDelete();
        });
    }

    /**
     * 旧来のカード削除制約へ戻せるよう外部キー定義を巻き戻す。
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['fincode_card_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('fincode_card_id')
                ->references('fincode_card_id')
                ->on('fincode_cards')
                ->restrictOnDelete();
        });
    }
};
