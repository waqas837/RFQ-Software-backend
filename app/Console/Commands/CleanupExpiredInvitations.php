<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupplierInvitation;

class CleanupExpiredInvitations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invitations:cleanup-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired supplier invitations as expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredCount = SupplierInvitation::expired()->count();
        
        if ($expiredCount > 0) {
            SupplierInvitation::expired()->update(['status' => 'expired']);
            $this->info("Marked {$expiredCount} expired invitations as expired.");
        } else {
            $this->info('No expired invitations found.');
        }

        return 0;
    }
}