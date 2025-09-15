<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin user for created_by
        $admin = User::where('email', 'admin@rfqsystem.com')->first();

        if (!$admin) {
            $this->command->error('Admin user not found. Please run UserSeeder first.');
            return;
        }

        $templates = [
            [
                'name' => 'RFQ Invitation Template',
                'slug' => 'rfq-invitation-template',
                'subject' => 'New RFQ Invitation: {{rfq_title}}',
                'content' => '<h2>RFQ Invitation</h2><p>Dear {{supplier_name}},</p><p>You have been invited to submit a bid for: {{rfq_title}}</p><p>Deadline: {{deadline}}</p><p>Buyer: {{buyer_name}}</p><p><a href="{{rfq_link}}">View RFQ & Submit Bid</a></p>',
                'type' => 'rfq_invitation',
                'placeholders' => ['supplier_name', 'rfq_title', 'rfq_description', 'deadline', 'buyer_name', 'rfq_link', 'contact_email'],
                'is_active' => true,
                'is_default' => true,
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Bid Confirmation Template',
                'slug' => 'bid-confirmation-template',
                'subject' => 'Bid Confirmation: {{rfq_title}}',
                'content' => '<h2>Bid Confirmation</h2><p>Dear {{supplier_name}},</p><p>Thank you for submitting your bid for: {{rfq_title}}</p><p>Bid Amount: {{bid_amount}}</p><p>Confirmation Number: {{confirmation_number}}</p>',
                'type' => 'bid_confirmation',
                'placeholders' => ['supplier_name', 'rfq_title', 'bid_amount', 'submission_date', 'confirmation_number', 'rfq_deadline'],
                'is_active' => true,
                'is_default' => true,
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Purchase Order Template',
                'slug' => 'po-generated-template',
                'subject' => 'Purchase Order Generated: {{po_number}}',
                'content' => '<h2>Purchase Order Generated</h2><p>Dear {{supplier_name}},</p><p>A purchase order has been generated: {{po_number}}</p><p>Amount: {{po_amount}}</p><p>Delivery Date: {{delivery_date}}</p>',
                'type' => 'po_generated',
                'placeholders' => ['supplier_name', 'po_number', 'po_amount', 'rfq_title', 'delivery_date', 'buyer_name', 'po_link'],
                'is_active' => true,
                'is_default' => true,
                'created_by' => $admin->id,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::create($template);
        }

        $this->command->info('Email templates seeded successfully!');
    }
}
