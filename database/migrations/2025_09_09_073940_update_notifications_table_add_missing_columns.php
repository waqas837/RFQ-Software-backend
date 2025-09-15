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
        Schema::table('notifications', function (Blueprint $table) {
            // Add missing columns for our notification system
            $table->unsignedBigInteger('related_user_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('related_entity_id')->nullable()->after('related_user_id');
            $table->string('related_entity_type')->nullable()->after('related_entity_id');
            $table->boolean('is_read')->default(false)->after('status');
            $table->boolean('is_email_sent')->default(false)->after('is_read');
            $table->timestamp('email_sent_at')->nullable()->after('is_email_sent');
            
            // Add foreign key constraints
            $table->foreign('related_user_id')->references('id')->on('users')->onDelete('set null');
            
            // Add indexes for better performance
            $table->index(['user_id', 'is_read']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['related_user_id']);
            
            // Drop indexes
            $table->dropIndex(['user_id', 'is_read']);
            $table->dropIndex(['type', 'created_at']);
            
            // Drop columns
            $table->dropColumn([
                'related_user_id',
                'related_entity_id', 
                'related_entity_type',
                'is_read',
                'is_email_sent',
                'email_sent_at'
            ]);
        });
    }
};
