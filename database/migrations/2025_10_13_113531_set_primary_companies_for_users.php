<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set the first company as primary for each user
        // Using a temporary table to avoid MySQL error 1093
        DB::statement("
            CREATE TEMPORARY TABLE temp_primary_companies AS
            SELECT MIN(id) as id
            FROM user_company
            GROUP BY user_id
        ");
        
        DB::statement("
            UPDATE user_company 
            SET is_primary = 1 
            WHERE id IN (SELECT id FROM temp_primary_companies)
        ");
        
        DB::statement("DROP TEMPORARY TABLE temp_primary_companies");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset all primary flags
        DB::table('user_company')->update(['is_primary' => 0]);
    }
};