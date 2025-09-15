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
        Schema::create('bid_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained()->onDelete('cascade');
            $table->foreignId('rfq_item_id')->constrained()->onDelete('cascade');
            $table->string('item_name');
            $table->text('item_description')->nullable();
            $table->integer('quantity');
            $table->string('unit_of_measure');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('delivery_date')->nullable();
            $table->text('technical_specifications')->nullable();
            $table->text('brand_model')->nullable();
            $table->text('warranty')->nullable();
            $table->json('custom_fields')->nullable();
            $table->boolean('is_available')->default(true);
            $table->text('availability_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_items');
    }
};
