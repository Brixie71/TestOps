<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_email_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('email', 255)->unique();
            $table->string('status', 20);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        $timestamp = now();

        DB::table('users')
            ->select(['id', 'email', 'email_verified_at'])
            ->orderBy('id')
            ->chunk(500, function ($users) use ($timestamp): void {
                $records = $users->map(fn ($user) => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => 'current',
                    'verified_at' => $user->email_verified_at,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all();

                DB::table('user_email_addresses')->insert($records);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_email_addresses');
    }
};
