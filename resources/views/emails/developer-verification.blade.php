<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Account Verification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .title {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #6b7280;
            color: #ffffff;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin: 20px 0;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #4b5563;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        .highlight {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #6b7280;
        }
        .api-info {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .api-info h3 {
            color: #0369a1;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">SIMPLY PROCURE</div>
            <h1 class="title">Developer Account Verification</h1>
        </div>

        <div class="content">
            <p>Hello {{ $user->name }},</p>
            
            <p>Welcome to the Simply Procure Developer Portal! Thank you for registering as a developer.</p>
            
            <p>To complete your registration and start using our API, please verify your email address by clicking the button below:</p>
            
            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="button">Verify Developer Account</a>
            </div>
            
            <div class="highlight">
                <strong>Important:</strong> This verification link will expire in 24 hours. If you don't verify your account within this time, you'll need to register again.
            </div>
            
            <div class="api-info">
                <h3>🚀 What's Next?</h3>
                <p>Once verified, you'll have access to:</p>
                <ul>
                    <li><strong>API Documentation:</strong> Complete reference for all endpoints</li>
                    <li><strong>API Keys:</strong> Generate and manage your API keys</li>
                    <li><strong>Usage Analytics:</strong> Track your API usage and performance</li>
                    <li><strong>Rate Limits:</strong> File uploads (10/min), deletions (20/min), standard limits for other operations</li>
                    <li><strong>Developer Support:</strong> Get help from our technical team</li>
                </ul>
            </div>
            
            <p>If you didn't create a developer account, please ignore this email.</p>
            
            <p>Best regards,<br>
            The Simply Procure Team</p>
        </div>

        <div class="footer">
            <p>This email was sent to {{ $user->email }}. If you have any questions, please contact our support team.</p>
            <p>&copy; {{ date('Y') }} Simply Procure. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
