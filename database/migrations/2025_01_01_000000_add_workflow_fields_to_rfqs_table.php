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
        Schema::table('rfqs', function (Blueprint $table) {
            // Add workflow-related fields
            $table->foreignId('awarded_supplier_id')->nullable()->constrained('companies')->onDelete('set null');
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('workflow_history')->nullable(); // Store status change history
            $table->json('approval_workflow')->nullable(); // Store approval workflow configuration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropForeign(['awarded_supplier_id']);
            $table->dropColumn([
                'awarded_supplier_id',
                'awarded_at',
                'completed_at',
                'cancelled_at',
                'cancellation_reason',
                'workflow_history',
                'approval_workflow'
            ]);
        });
    }
};
