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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3); // Base currency (e.g., USD)
            $table->string('to_currency', 3);   // Target currency (e.g., EUR)
            $table->decimal('rate', 15, 6);     // Exchange rate
            $table->date('date');               // Rate date
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['from_currency', 'to_currency', 'date']);
            $table->unique(['from_currency', 'to_currency', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
