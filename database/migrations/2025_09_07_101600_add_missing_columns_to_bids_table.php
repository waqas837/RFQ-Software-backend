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
            // Add supplier_id column (rename from submitted_by)
            $table->foreignId('supplier_id')->nullable()->after('supplier_company_id')->constrained('users')->onDelete('cascade');
            
            // Add delivery_time column
            $table->integer('delivery_time')->nullable()->after('total_amount');
            
            // Add is_active column
            $table->boolean('is_active')->default(true)->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'delivery_time', 'is_active']);
        });
    }
};
