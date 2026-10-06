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
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('project_role')->comment('Initial values: project_manager, site_supervisor, employee, other');
            $table->boolean('can_view_financials')->default(false);
            $table->boolean('can_manage_documents')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'project_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};
