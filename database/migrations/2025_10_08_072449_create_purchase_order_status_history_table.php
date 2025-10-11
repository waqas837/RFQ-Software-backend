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
        Schema::create('purchase_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->string('status_from')->nullable();
            $table->string('status_to');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('changed_at');
            $table->json('metadata')->nullable(); // Store additional data like approval details, etc.
            $table->timestamps();
            
            $table->index(['purchase_order_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_status_history');
    }
};