<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_order_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_line_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code')->nullable()->index();
            $table->text('description');
            $table->bigInteger('amount')->default(0)->comment('Signed integer cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['change_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_order_line_items');
    }
};
