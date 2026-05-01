<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contacts');
    }

    public function down(): void
    {
        // 当該テーブルはこのコードベースでは作成されないため、ロールバックは何もしない
    }
};
