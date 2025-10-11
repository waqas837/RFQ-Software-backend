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
        Schema::table('bids', function (Blueprint $table) {
            // Update score columns to allow more precision
            $table->decimal('technical_score', 5, 2)->nullable()->change();
            $table->decimal('commercial_score', 5, 2)->nullable()->change();
            $table->decimal('delivery_score', 5, 2)->nullable()->change();
            $table->decimal('total_score', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            // Revert to original precision
            $table->decimal('technical_score', 3, 2)->nullable()->change();
            $table->decimal('commercial_score', 3, 2)->nullable()->change();
            $table->decimal('delivery_score', 3, 2)->nullable()->change();
            $table->decimal('total_score', 3, 2)->nullable()->change();
        });
    }
};
