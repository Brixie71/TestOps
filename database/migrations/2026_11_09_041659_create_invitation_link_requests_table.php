<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track each invitation link (or password-setup link) that was generated
     * and emailed. One row per emailed credential so resends, rate limits,
     * and single-use acceptance can be enforced. Only the token hash and an
     * independent delivery linkage are stored; the raw token never is.
     */
    public function up(): void
    {
        Schema::create('invitation_link_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_invitation_id')
                ->constrained('user_invitations')
                ->cascadeOnDelete();

            $table->string('recipient_email', 255);
            $table->unsignedInteger('credential_generation');
            $table->string('method', 30);

            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');

            $table->timestamp('consumed_at')->nullable();

            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();

            $table->foreignId('delivery_id')
                ->nullable()
                ->constrained('notification_deliveries')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_link_requests');
    }
};
