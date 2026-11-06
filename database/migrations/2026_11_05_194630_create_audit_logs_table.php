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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor identity snapshots.
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();

            // Target identity snapshots.
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('target_name')->nullable();
            $table->string('target_email')->nullable();

            // Event details.
            $table->string('action', 100);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('reason')->nullable();

            // Audit records are immutable historical events.
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_user_id');
            $table->index('target_user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
