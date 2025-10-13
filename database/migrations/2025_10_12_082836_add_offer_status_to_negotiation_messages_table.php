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
        Schema::table('negotiation_messages', function (Blueprint $table) {
            $table->string('offer_status')->nullable()->after('offer_data'); // accepted, rejected, cancelled
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negotiation_messages', function (Blueprint $table) {
            $table->dropColumn('offer_status');
        });
    }
};
