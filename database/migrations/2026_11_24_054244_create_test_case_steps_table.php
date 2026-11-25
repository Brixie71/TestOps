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
        Schema::create('test_case_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('action');
            $table->text('expected_result')->nullable();
            $table->timestamps();

            $table->unique(['test_case_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_case_steps');
    }
};
