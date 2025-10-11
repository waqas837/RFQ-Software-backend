<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Rfq;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Log;

class AutoCloseExpiredRfqs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rfq:auto-close-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close RFQs that have passed their bidding deadline';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting auto-close process for expired RFQs...');

        // Find RFQs that are open for bidding but have passed their deadline
        $expiredRfqs = Rfq::where('status', 'bidding_open')
            ->where('bid_deadline', '<', now())
            ->get();

        if ($expiredRfqs->isEmpty()) {
            $this->info('No expired RFQs found.');
            return;
        }

        $this->info("Found {$expiredRfqs->count()} expired RFQ(s) to close.");

        $closedCount = 0;
        $failedCount = 0;

        foreach ($expiredRfqs as $rfq) {
            try {
                // Create a system user for automatic transitions
                $systemUser = new \App\Models\User();
                $systemUser->id = 0;
                $systemUser->name = 'System';
                $systemUser->email = 'system@rfq.com';
                $systemUser->role = 'admin';

                // Transition to bidding_closed
                if ($rfq->transitionTo('bidding_closed', $systemUser, [
                    'auto_closed' => true,
                    'closed_at' => now(),
                    'reason' => 'Bidding deadline has passed'
                ])) {
                    $closedCount++;
                    $this->info("✓ Closed RFQ: {$rfq->title} (ID: {$rfq->id})");
                    Log::info("Auto-closed RFQ {$rfq->id} - deadline passed");
                } else {
                    $failedCount++;
                    $this->error("✗ Failed to close RFQ: {$rfq->title} (ID: {$rfq->id})");
                }
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ Error closing RFQ {$rfq->id}: " . $e->getMessage());
                Log::error("Failed to auto-close RFQ {$rfq->id}: " . $e->getMessage());
            }
        }

        $this->info("Auto-close process completed:");
        $this->info("  - Successfully closed: {$closedCount}");
        $this->info("  - Failed to close: {$failedCount}");

        if ($closedCount > 0) {
            Log::info("Auto-close command completed: {$closedCount} RFQs closed, {$failedCount} failed");
        }
    }
}