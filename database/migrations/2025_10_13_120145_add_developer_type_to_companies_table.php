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
        // For SQLite, we need to recreate the table with the new enum values
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('type', ['buyer', 'supplier', 'both', 'developer'])->default('buyer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('type', ['buyer', 'supplier', 'both'])->default('buyer');
        });
    }
};
