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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('documentable');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->index()->comment('Initial values: contract, change_order, client_invoice, subcontractor_invoice, plan, compliance, photo, general');
            $table->text('external_url')->nullable();
            $table->string('google_drive_file_id')->nullable()->index();
            $table->string('google_drive_folder_id')->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
