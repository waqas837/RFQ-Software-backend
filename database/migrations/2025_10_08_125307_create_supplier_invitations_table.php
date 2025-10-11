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
        Schema::create('supplier_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->foreignId('rfq_id')->constrained()->onDelete('cascade');
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->string('company_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->enum('status', ['pending', 'registered', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('registered_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['email', 'status']);
            $table->index(['token']);
            $table->index(['rfq_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invitations');
    }
};