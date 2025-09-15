<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmailService;
use App\Models\User;
use App\Models\Rfq;
use App\Models\Bid;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {type} {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $email = $this->argument('email');

        $this->info("Testing email type: {$type} to: {$email}");

        try {
            switch ($type) {
                case 'template':
                    $this->testTemplateEmail($email);
                    break;
                case 'rfq-invitation':
                    $this->testRfqInvitation($email);
                    break;
                case 'bid-confirmation':
                    $this->testBidConfirmation($email);
                    break;
                default:
                    $this->error("Unknown email type: {$type}");
                    return 1;
            }

            $this->info("Email test completed successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error("Email test failed: " . $e->getMessage());
            return 1;
        }
    }

    private function testTemplateEmail($email)
    {
        $data = [
            'user_name' => 'Test User',
            'rfq_title' => 'Test RFQ',
            'deadline' => 'December 31, 2024 at 5:00 PM',
            'supplier_name' => 'Test Supplier',
            'bid_amount' => '$1,500.00',
            'submission_date' => 'December 15, 2024 at 2:30 PM',
        ];

        $this->info("Testing Gmail SMTP connection...");
        $result = EmailService::sendTemplateEmail('rfq-invitation-template', $email, $data);
        
        if ($result) {
            $this->info("✅ Template email sent successfully via Gmail SMTP");
        } else {
            $this->error("❌ Failed to send template email via Gmail SMTP");
        }
    }

    private function testRfqInvitation($email)
    {
        // Create test data
        $buyer = User::where('role', 'buyer')->first();
        $supplier = User::where('role', 'supplier')->first();
        $rfq = Rfq::first();

        if (!$buyer || !$supplier || !$rfq) {
            $this->error("Test data not found. Please ensure you have users and RFQs in the database.");
            return;
        }

        $result = EmailService::sendRfqInvitation($rfq, [$supplier], $buyer);
        
        if ($result > 0) {
            $this->info("RFQ invitation sent successfully");
        } else {
            $this->error("Failed to send RFQ invitation");
        }
    }

    private function testBidConfirmation($email)
    {
        // Create test data
        $supplier = User::where('role', 'supplier')->first();
        $rfq = Rfq::first();
        $bid = Bid::first();

        if (!$supplier || !$rfq || !$bid) {
            $this->error("Test data not found. Please ensure you have users, RFQs, and bids in the database.");
            return;
        }

        $result = EmailService::sendBidConfirmation($bid, $supplier, $rfq);
        
        if ($result) {
            $this->info("Bid confirmation sent successfully");
        } else {
            $this->error("Failed to send bid confirmation");
        }
    }
}
