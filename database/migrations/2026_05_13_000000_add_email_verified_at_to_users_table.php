<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * メール所有確認 (MustVerifyEmail) を導入するため、検証時刻を保持するカラムを追加する。
     * 既存ユーザーは未検証として残るので、運用者側で必要に応じて再送信または手動で
     * UPDATE users SET email_verified_at = NOW() を実行すること。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }

    /**
     * 検証経路を無効化する場合に列を取り戻せるよう、ロールバックでカラムを落とす。
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
