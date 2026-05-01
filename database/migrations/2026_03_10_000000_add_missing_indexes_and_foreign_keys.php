<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * subscriptions.fincode_card_id の外部キー制約・インデックス追加、
     * fincode_cards.user_id の単体インデックス追加。
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('fincode_card_id')
                ->references('fincode_card_id')
                ->on('fincode_cards')
                ->restrictOnDelete();
        });

        Schema::table('fincode_cards', function (Blueprint $table) {
            $table->index('user_id', 'fincode_cards_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['fincode_card_id']);
        });

        Schema::table('fincode_cards', function (Blueprint $table) {
            $table->dropIndex('fincode_cards_user_id_index');
        });
    }
};
