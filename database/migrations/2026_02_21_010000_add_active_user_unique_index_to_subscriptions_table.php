<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB レベルでも二重契約を防ぐため有効契約の一意制約を追加する。
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->bigInteger('active_user_id')
                ->unsigned()
                ->nullable()
                ->virtualAs("CASE WHEN `status` = 'active' AND `deleted_at` IS NULL THEN `user_id` ELSE NULL END");
            $table->unique('active_user_id', 'subscriptions_active_user_id_unique');
        });
    }

    /**
     * 一意制約追加を巻き戻せるよう補助カラムごと削除する。
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_active_user_id_unique');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('active_user_id');
        });
    }
};
