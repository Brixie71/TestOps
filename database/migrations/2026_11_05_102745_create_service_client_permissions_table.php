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
        Schema::create('service_client_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('oauth_client_id');
            $table->string('permission');
            $table->timestamps();

            // A service client receives each application permission at most once.
            $table->unique(['oauth_client_id', 'permission']);
            $table->foreign('oauth_client_id')
                ->references('id')
                ->on('oauth_clients')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_client_permissions');
    }

    /**
     * Get the migration connection name.
     *
     * The table stores service-client authorization and holds a foreign key to
     * `oauth_clients`, so it must live on the same connection as Passport.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
