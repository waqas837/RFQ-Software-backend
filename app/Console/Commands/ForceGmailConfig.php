<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class ForceGmailConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:force-gmail {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force Gmail configuration and test connection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🔧 Forcing Gmail Configuration...");
        $this->info("📧 Testing with email: {$email}");
        $this->newLine();

        try {
            // Force Gmail configuration
            $this->forceGmailConfig();
            $this->newLine();

            // Test connection
            $this->testGmailConnection($email);
            
        } catch (\Exception $e) {
            $this->error("❌ Failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function forceGmailConfig()
    {
        $this->info("⚙️ Setting Gmail Configuration...");
        
        // Force the mail configuration
        Config::set('mail.default', 'gmail');
        Config::set('mail.mailers.gmail.host', 'smtp.gmail.com');
        Config::set('mail.mailers.gmail.port', 587);
        Config::set('mail.mailers.gmail.encryption', 'tls');
        Config::set('mail.mailers.gmail.username', 'waqaskhanbughlani1124@gmail.com');
        Config::set('mail.mailers.gmail.password', 'iokw hgvc tyvx lvfl');
        Config::set('mail.mailers.gmail.timeout', 60);
        
        $this->info("✅ Gmail configuration forced");
        
        // Show current config
        $this->line("  📍 Host: " . Config::get('mail.mailers.gmail.host'));
        $this->line("  📍 Port: " . Config::get('mail.mailers.gmail.port'));
        $this->line("  📍 Encryption: " . Config::get('mail.mailers.gmail.encryption'));
        $this->line("  📍 Username: " . Config::get('mail.mailers.gmail.username'));
        $this->line("  📍 Password: ***SET***");
    }

    private function testGmailConnection($email)
    {
        $this->info("🧪 Testing Gmail Connection...");
        
        try {
            // Create a simple mailable
            $mailable = new class($email) extends \Illuminate\Mail\Mailable {
                private $testEmail;
                
                public function __construct($email) {
                    $this->testEmail = $email;
                }
                
                public function build() {
                    return $this->view('emails.test')
                              ->subject('🔧 Forced Gmail Test - RFQ Software')
                              ->with(['email' => $this->testEmail]);
                }
            };

            // Send using forced Gmail config
            Mail::mailer('gmail')->to($email)->send($mailable);
            
            $this->info("✅ Gmail connection test successful!");
            $this->info("📧 Check your inbox at: {$email}");
            
        } catch (\Exception $e) {
            $this->error("❌ Gmail connection failed: " . $e->getMessage());
            throw $e;
        }
    }
}
