<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestGmailConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-gmail {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Gmail SMTP connection with a simple email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🧪 Testing Gmail SMTP Connection...");
        $this->info("📧 Sending test email to: {$email}");
        $this->newLine();

        try {
            // Test 1: Check configuration
            $this->checkConfiguration();
            $this->newLine();

            // Test 2: Send simple email
            $this->sendSimpleEmail($email);
            
        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            $this->error("📁 File: " . $e->getFile() . ":" . $e->getLine());
            return 1;
        }

        return 0;
    }

    private function checkConfiguration()
    {
        $this->info("🔍 Checking Gmail Configuration:");
        
        $config = [
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_HOST' => config('mail.mailers.gmail.host'),
            'MAIL_PORT' => config('mail.mailers.gmail.port'),
            'MAIL_ENCRYPTION' => config('mail.mailers.gmail.encryption'),
            'MAIL_USERNAME' => config('mail.mailers.gmail.username'),
            'MAIL_PASSWORD' => config('mail.mailers.gmail.password') ? '***SET***' : 'NOT SET',
        ];

        foreach ($config as $key => $value) {
            $status = $value ? '✅' : '❌';
            $this->line("  {$status} {$key}: {$value}");
        }

        // Check if Gmail mailer is properly configured
        if (!config('mail.mailers.gmail')) {
            throw new \Exception("Gmail mailer configuration not found!");
        }

        if (!config('mail.mailers.gmail.username') || !config('mail.mailers.gmail.password')) {
            throw new \Exception("Gmail username or password not set!");
        }
    }

    private function sendSimpleEmail($email)
    {
        $this->info("📤 Sending Simple Test Email...");
        
        try {
            // Create a simple mailable
            $mailable = new class($email) extends \Illuminate\Mail\Mailable {
                private $testEmail;
                
                public function __construct($email) {
                    $this->testEmail = $email;
                }
                
                public function build() {
                    return $this->view('emails.test')
                              ->subject('🧪 Gmail SMTP Test - RFQ Software')
                              ->with(['email' => $this->testEmail]);
                }
            };

            // Send using Gmail mailer
            Mail::mailer('gmail')->to($email)->send($mailable);
            
            $this->info("✅ Test email sent successfully!");
            $this->info("📧 Check your inbox at: {$email}");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to send test email: " . $e->getMessage());
            throw $e;
        }
    }
}
