<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend invitations for the account creation module.
     *
     * - method: how the account offer was issued (invitation_link or
     *   direct_account_setup), with acceptance_flow as the data-supported
     *   snapshot of the configured acceptance experience.
     * - credential_generation: incremented on every credential rotation so
     *   the live credential set can be identified.
     * - recovery_code_*: hashed recovery code, expiry, failed-attempt
     *   counter and lockout window.
     * - sent_count / last_sent_at: delivery bookkeeping.
     * - cancelled_reason / cancelled_by: why the invitation was cancelled.
     * - expired_notified_at: set once the expiry notification has been sent.
     */
    public function up(): void
    {
        Schema::table('user_invitations', function (Blueprint $table): void {
            $table->string('method', 30)->default('invitation_link');
            $table->string('acceptance_flow', 30)->default('password_setup_only');

            $table->unsignedInteger('credential_generation')->default(1);

            $table->string('recovery_code_hash', 64)->nullable();
            $table->timestamp('recovery_code_expires_at')->nullable();

            $table->unsignedSmallInteger('recovery_attempts')->default(0);
            $table->timestamp('recovery_locked_until')->nullable();

            $table->unsignedSmallInteger('sent_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();

            $table->string('cancelled_reason', 255)->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('expired_notified_at')->nullable();

            $table->index(['invited_email', 'cancelled_reason']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_invitations', function (Blueprint $table): void {
            $table->dropIndex(['invited_email', 'cancelled_reason']);

            $table->dropConstrainedForeignId('cancelled_by');

            $table->dropColumn([
                'method',
                'acceptance_flow',
                'credential_generation',
                'recovery_code_hash',
                'recovery_code_expires_at',
                'recovery_attempts',
                'recovery_locked_until',
                'sent_count',
                'last_sent_at',
                'cancelled_reason',
                'expired_notified_at',
            ]);
        });
    }
};
