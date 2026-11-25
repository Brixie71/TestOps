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
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            // One current result per frozen case: re-recording updates it.
            $table->foreignId('test_run_case_id')->constrained('test_run_cases')->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('notes')->nullable();
            // Authorship survives account deletion through the nullable link.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique('test_run_case_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
