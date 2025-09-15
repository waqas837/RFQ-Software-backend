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
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->string('bid_number')->unique();
            $table->foreignId('rfq_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'accepted',
                'rejected',
                'awarded',
                'withdrawn'
            ])->default('draft');
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('proposed_delivery_date')->nullable();
            $table->text('technical_proposal')->nullable();
            $table->text('commercial_terms')->nullable();
            $table->longText('terms_conditions')->nullable();
            $table->json('attachments')->nullable();
            $table->json('custom_fields')->nullable();
            $table->boolean('is_compliant')->default(true);
            $table->text('compliance_notes')->nullable();
            $table->decimal('technical_score', 5, 2)->nullable();
            $table->decimal('commercial_score', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->text('evaluation_notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
