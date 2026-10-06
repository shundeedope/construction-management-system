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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();
            $table->string('name')->index();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('US');
            $table->date('start_date')->nullable()->index();
            $table->date('expected_completion_date')->nullable()->index();
            $table->date('actual_completion_date')->nullable();
            $table->string('status')->default('planning')->index()->comment('Initial values: planning, active, on_hold, completed, cancelled');
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
            $table->index(['project_manager_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(static fn () => Schema::dropIfExists('projects'));
    }
};
