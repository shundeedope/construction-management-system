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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('type')->index()->comment('Initial values: client, subcontractor');
            $table->string('number')->unique();
            $table->string('status')->default('draft')->index()->comment('Initial values: draft, submitted, approved, sent, partially_paid, paid, rejected, void');
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->date('issue_date')->nullable()->index();
            $table->date('due_date')->nullable()->index();
            $table->bigInteger('subtotal')->default(0)->comment('Signed integer cents');
            $table->bigInteger('retention_amount')->default(0)->comment('Signed integer cents');
            $table->bigInteger('total')->default(0)->comment('Signed integer cents');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('void_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'type', 'status']);
            $table->index(['contract_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
