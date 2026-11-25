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
        // Optional reconstruction data for retryable deliveries. Project
        // activity stores the event and the referenced ids so a failed
        // message can be rebuilt without persisting its body.
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->json('context')->nullable()->after('user_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
