<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account creation supports Email Only invitations: the display name is
     * optional. The user's email remains the unique identity anchor, and
     * interfaces fall back to the email prefix when the name is absent.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
        });
    }

    /**
     * Restore the original NOT NULL constraint.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
        });
    }
};
