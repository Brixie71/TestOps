<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email-verification security controls (§10.3-§10.8):
 *
 * - issued_at records the exact issuance time so rolling-window limits never
 *   depend on fixed clock hours and stay deterministic when issuance time is
 *   injected.
 * - requested_ip lets the configurable per-IP hourly limit count issuances.
 * - failed_attempts is the token-level counter (a replacement token starts at
 *   zero because it is a new row).
 * - email_verification_attempt_counters holds the request-level counter that
 *   survives token replacement and carries the 30-minute lockout.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_verification_requests', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->unsignedSmallInteger('failed_attempts')->default(0);

            $table->index(['user_id', 'issued_at']);
            $table->index(['requested_ip', 'issued_at']);
        });

        // Existing rows predate the column; their creation time is the best
        // available issuance time and keeps rolling-window counts correct.
        DB::table('email_verification_requests')
            ->whereNull('issued_at')
            ->update(['issued_at' => DB::raw('created_at')]);

        Schema::create('email_verification_attempt_counters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('purpose', 30);
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_attempt_counters');

        Schema::table('email_verification_requests', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'issued_at']);
            $table->dropIndex(['requested_ip', 'issued_at']);

            $table->dropColumn(['issued_at', 'requested_ip', 'failed_attempts']);
        });
    }
};
