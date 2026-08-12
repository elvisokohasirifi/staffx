<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->longText('body');
            $table->json('recipient_user_ids')->nullable();
            $table->json('recipient_roles')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignUuid('sent_by_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();

            $table->index(['sent_by_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_notifications');
    }
};
