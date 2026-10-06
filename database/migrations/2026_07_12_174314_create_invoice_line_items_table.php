<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_line_item_id')->constrained()->restrictOnDelete();
            $table->bigInteger('scheduled_value')->default(0)->comment('Signed integer cents');
            $table->bigInteger('previously_billed')->default(0)->comment('Signed integer cents');
            $table->bigInteger('work_completed_this_period')->default(0)->comment('Signed integer cents');
            $table->bigInteger('materials_stored')->default(0)->comment('Signed integer cents');
            $table->bigInteger('retention_amount')->default(0)->comment('Signed integer cents');
            $table->bigInteger('current_due')->default(0)->comment('Signed integer cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['invoice_id', 'sort_order']);
            $table->index(['contract_line_item_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
