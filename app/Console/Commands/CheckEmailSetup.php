<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Config;

class CheckEmailSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check email setup status including templates and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking Email Setup Status...');
        $this->newLine();

        // Check mail configuration
        $this->checkMailConfiguration();
        $this->newLine();

        // Check database templates
        $this->checkEmailTemplates();
        $this->newLine();

        // Check Gmail specific settings
        $this->checkGmailSettings();
    }

    private function checkMailConfiguration()
    {
        $this->info('📧 Mail Configuration:');
        
        $config = [
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_HOST' => config('mail.mailers.smtp.host'),
            'MAIL_PORT' => config('mail.mailers.smtp.port'),
            'MAIL_ENCRYPTION' => config('mail.mailers.smtp.encryption'),
            'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
            'MAIL_PASSWORD' => config('mail.mailers.smtp.password') ? '***SET***' : 'NOT SET',
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
            'MAIL_FROM_NAME' => config('mail.from.name'),
        ];

        foreach ($config as $key => $value) {
            $status = $value ? '✅' : '❌';
            $this->line("  {$status} {$key}: {$value}");
        }
    }

    private function checkEmailTemplates()
    {
        $this->info('📝 Email Templates in Database:');
        
        $templates = EmailTemplate::all();
        
        if ($templates->count() === 0) {
            $this->error('  ❌ No email templates found in database');
            $this->line('  💡 Run: php artisan db:seed --class=EmailTemplateSeeder');
            return;
        }

        $this->line("  ✅ Found {$templates->count()} email templates:");
        
        foreach ($templates as $template) {
            $this->line("    • {$template->name} (slug: {$template->slug})");
        }

        // Check for specific template
        $rfqTemplate = EmailTemplate::getLatestBySlug('rfq-invitation-template');
        if ($rfqTemplate) {
            $this->line("  ✅ RFQ invitation template found");
        } else {
            $this->error("  ❌ RFQ invitation template not found");
        }
    }

    private function checkGmailSettings()
    {
        $this->info('🔐 Gmail Specific Settings:');
        
        $gmailConfig = config('mail.mailers.gmail');
        
        if (!$gmailConfig) {
            $this->error('  ❌ Gmail mailer configuration not found');
            return;
        }

        $this->line("  ✅ Gmail mailer configured");
        $this->line("  📍 Host: {$gmailConfig['host']}");
        $this->line("  📍 Port: {$gmailConfig['port']}");
        $this->line("  📍 Encryption: {$gmailConfig['encryption']}");
        $this->line("  📍 Username: {$gmailConfig['username']}");
        $this->line("  📍 Password: " . ($gmailConfig['password'] ? '***SET***' : 'NOT SET'));
    }
}
