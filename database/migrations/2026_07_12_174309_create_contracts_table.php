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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index()->comment('Initial values: owner, subcontract, purchase_order');
            $table->string('number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index()->comment('Initial values: draft, pending_approval, executed, closed, cancelled');
            $table->bigInteger('original_amount')->default(0)->comment('Signed integer cents');
            $table->unsignedInteger('retention_basis_points')->default(0)->comment('1 percent equals 100 basis points');
            $table->timestamp('executed_at')->nullable()->index();
            $table->date('start_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'type', 'status']);
            $table->index(['client_id', 'type']);
            $table->index(['vendor_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
