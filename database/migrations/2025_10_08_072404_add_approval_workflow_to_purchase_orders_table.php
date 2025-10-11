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
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Add approval workflow fields
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('approval_notes');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null')->after('rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            
            // Add modification tracking
            $table->json('modification_history')->nullable()->after('rejected_at');
            $table->foreignId('last_modified_by')->nullable()->constrained('users')->onDelete('set null')->after('modification_history');
            $table->timestamp('last_modified_at')->nullable()->after('last_modified_by');
            
            // Add enhanced tracking fields
            $table->text('internal_notes')->nullable()->after('last_modified_at');
            $table->json('status_history')->nullable()->after('internal_notes');
            $table->boolean('requires_approval')->default(true)->after('status_history');
            $table->decimal('approved_amount', 15, 2)->nullable()->after('requires_approval');
            
            // Add workflow control fields
            $table->enum('approval_level', ['single', 'multi'])->default('single')->after('approved_amount');
            $table->json('approval_chain')->nullable()->after('approval_level');
            $table->integer('current_approval_step')->default(0)->after('approval_chain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'approval_notes',
                'rejection_reason',
                'rejected_by',
                'rejected_at',
                'modification_history',
                'last_modified_by',
                'last_modified_at',
                'internal_notes',
                'status_history',
                'requires_approval',
                'approved_amount',
                'approval_level',
                'approval_chain',
                'current_approval_step'
            ]);
        });
    }
};