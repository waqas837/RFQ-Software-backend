<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Gmail SMTP Test</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
        .success { color: #4CAF50; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Gmail SMTP Test</h1>
            <p>RFQ Software - Email System Test</p>
        </div>
        
        <div class="content">
            <h2 class="success">✅ Gmail SMTP Connection Successful!</h2>
            
            <p>This is a test email to verify that your Gmail SMTP configuration is working correctly.</p>
            
            <div class="info">
                <strong>Test Details:</strong><br>
                • Sent to: {{ $email }}<br>
                • Timestamp: {{ now()->format('F j, Y \a\t g:i A') }}<br>
                • Mailer: Gmail SMTP<br>
                • Status: Success
            </div>
            
            <p>Your email system is now properly configured and ready to send emails for:</p>
            <ul>
                <li>RFQ Invitations</li>
                <li>Bid Confirmations</li>
                <li>Purchase Orders</li>
                <li>Status Updates</li>
                <li>Deadline Reminders</li>
            </ul>
            
            <p><em>This is an automated test email. Please delete it after confirming receipt.</em></p>
        </div>
    </div>
</body>
</html>
