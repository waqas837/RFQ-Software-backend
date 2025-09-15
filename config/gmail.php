<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gmail SMTP Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the Gmail SMTP configuration for the RFQ Software.
    | Update your .env file with these exact values for Gmail integration.
    |
    */

    'gmail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => env('MAIL_USERNAME', 'waqaskhanbughlani1124@gmail.com'),
        'password' => env('MAIL_PASSWORD', 'iokw hgvc tyvx lvfl'),
        'timeout' => 60,
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gmail App Password Instructions
    |--------------------------------------------------------------------------
    |
    | 1. Enable 2-Factor Authentication on your Google Account
    | 2. Generate App Password: Security > 2-Step Verification > App passwords
    | 3. Select "Mail" and "Other (Custom name)"
    | 4. Name it "RFQ Software"
    | 5. Copy the 16-character password (remove spaces)
    |
    | Your .env file should contain:
    | MAIL_USERNAME=waqaskhanbughlani1124@gmail.com
    | MAIL_PASSWORD=iokw hgvc tyvx lvfl
    |
    */
];
