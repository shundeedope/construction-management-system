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
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('scope')->default('global');
            $table->string('format');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedSmallInteger('padding')->default(4);
            $table->string('reset_frequency')->default('never')->comment('Initial values: never, yearly');
            $table->string('current_period')->default('all');
            $table->timestamps();
            $table->unique(['key', 'scope', 'current_period']);
            $table->index(['key', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
