<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Rfq;

class CheckRFQDeadlines extends Command
{
    protected $signature = 'check:rfq-deadlines';
    protected $description = 'Check RFQ bid deadlines and fix expired ones';

    public function handle()
    {
        $this->info('=== Checking RFQ Deadlines ===');
        
        $rfqs = Rfq::all();
        
        foreach ($rfqs as $rfq) {
            $this->info("RFQ ID: {$rfq->id} - {$rfq->title}");
            $this->info("  Status: {$rfq->status}");
            $this->info("  Bid Deadline: {$rfq->bid_deadline}");
            $this->info("  Current Time: " . now());
            $this->info("  Is Accepting Bids: " . ($rfq->isAcceptingBids() ? 'YES' : 'NO'));
            $this->info("  Is Bidding Expired: " . ($rfq->isBiddingExpired() ? 'YES' : 'NO'));
            
            // Fix expired deadlines
            if ($rfq->isBiddingExpired()) {
                $this->info("  FIXING: Setting deadline to tomorrow");
                $rfq->bid_deadline = now()->addDay();
                $rfq->save();
                $this->info("  FIXED: New deadline is {$rfq->bid_deadline}");
            }
            
            $this->info('---');
        }
    }
}
