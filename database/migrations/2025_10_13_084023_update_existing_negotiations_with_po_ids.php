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
        // Update existing negotiations that have purchase orders but no purchase_order_id set
        \DB::statement("
            UPDATE negotiations 
            SET purchase_order_id = (
                SELECT po.id 
                FROM purchase_orders po 
                INNER JOIN bids b ON po.bid_id = b.id 
                WHERE b.id = negotiations.bid_id 
                LIMIT 1
            )
            WHERE purchase_order_id IS NULL 
            AND bid_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we can't determine which POs were linked before
        // The purchase_order_id column will remain as is
    }
};
