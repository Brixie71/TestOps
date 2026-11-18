<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purpose-aware email verification requests with an explicit lifecycle.
     *
     * States: issued (usable), consumed (used successfully), expired (passed
     * expiry without consumption), invalidated (revoked or superseded).
     *
     * Only a secure hash of the token is stored; the raw token never is.
     * At most one outstanding (issued) request may exist per user and
     * purpose, enforced by a partial unique index on both supported
     * engines.
     */
    public function up(): void
    {
        Schema::create('email_verification_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('purpose', 30);
            $table->string('email', 255);

            $table->string('token_hash', 64)->unique();
            $table->string('state', 20)->default('issued');

            $table->timestamp('expires_at');

            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();

            $table->timestamps();
        });

        $this->createOutstandingRequestUniqueIndex();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_requests');
    }

    /**
     * Enforce a single outstanding request per user and purpose. Both
     * PostgreSQL and SQLite support partial WHERE clauses on unique
     * indexes, so the same statement is used for every engine.
     */
    private function createOutstandingRequestUniqueIndex(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX email_verification_requests_outstanding_unique
                ON email_verification_requests (user_id, purpose)
                WHERE state = 'issued'",
        );
    }
};
