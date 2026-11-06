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
        Schema::create('notification_delivery_retries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('notification_delivery_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 20);
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->string('user_message', 255)->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('completed_at')->nullable();

            // The unique composite index both enforces one row per attempt
            // number and serves retry-history reads ordered by attempt.
            $table->unique(['notification_delivery_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_retries');
    }
};
