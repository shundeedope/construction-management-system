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
        Schema::create('contract_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('cost_code')->nullable()->index();
            $table->text('description');
            $table->decimal('quantity', 12, 3)->nullable();
            $table->string('unit')->nullable();
            $table->bigInteger('unit_price')->nullable()->comment('Signed integer cents');
            $table->bigInteger('amount')->default(0)->comment('Signed integer cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['contract_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_line_items');
    }
};
