<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forced password-reset machinery (§11.3): a pending-reset flag on the
     * account and purpose-built password reset requests with an explicit
     * lifecycle (issued, consumed, expired, invalidated), mirroring the
     * email verification request design. Only a secure hash of the token is
     * stored; the raw token never is.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('password_reset_required')->default(false);
        });

        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('token_hash', 64)->unique();
            $table->string('state', 20)->default('issued');

            $table->unsignedInteger('failed_attempts')->default(0);

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
        Schema::dropIfExists('password_reset_requests');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_reset_required');
        });
    }

    /**
     * Enforce a single outstanding (issued) reset request per user. Both
     * PostgreSQL and SQLite support partial WHERE clauses on unique
     * indexes, so the same statement is used for every engine.
     */
    private function createOutstandingRequestUniqueIndex(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX password_reset_requests_outstanding_unique
                ON password_reset_requests (user_id)
                WHERE state = 'issued'",
        );
    }
};
