<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e9ecef;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            font-size: 14px;
            color: #6c757d;
        }
        .button {
            display: inline-block;
            background: #007bff;
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button:hover {
            background: #0056b3;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Email Verification Required</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $userName }},</p>
        
        <p>You have requested to change your email address to <strong>{{ $newEmail }}</strong>.</p>
        
        <p>To complete this change, please click the button below to verify your new email address:</p>
        
        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>
        </div>
        
        <div class="warning">
            <strong>Important:</strong> This verification link will expire in 24 hours. If you don't verify your email within this time, you'll need to request a new verification email.
        </div>
        
        <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
        <p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px;">
            {{ $verificationUrl }}
        </p>
        
        <p>If you didn't request this email change, please ignore this email and your current email address will remain unchanged.</p>
        
        <p>Best regards,<br>RFQ System Team</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
    </div>
</body>
</html>
