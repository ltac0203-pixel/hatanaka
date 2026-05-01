<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('webhook_events');
    }

    public function down(): void
    {
        Schema::create('webhook_events', function ($table) {
            $table->id();
            $table->string('event')->comment('subscription.card.regist等');
            $table->string('fincode_event_id', 50)->unique();
            $table->string('idempotent_key', 36)->unique()->comment('UUID v4');
            $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'ignored'])->default('pending');
            $table->json('payload')->comment('Webhook全体のペイロード');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index(['event', 'status']);
            $table->index('idempotent_key');
            $table->index('created_at');
        });
    }
};
