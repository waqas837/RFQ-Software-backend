<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to RFQ System</title>
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
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .rfq-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .button {
            display: inline-block;
            background-color: #6b7280 !important;
            color: white !important;
            padding: 12px 24px;
            text-decoration: none !important;
            border-radius: 6px;
            margin: 15px 0;
        }
        .button:hover {
            background-color: #4b5563 !important;
            color: white !important;
        }
        a.button {
            color: white !important;
            text-decoration: none !important;
        }
        a.button:hover {
            color: white !important;
            text-decoration: none !important;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to RFQ System!</h1>
        <p>Your registration has been completed successfully</p>
    </div>

    <div class="content">
        <p>Hello {{ $user_name }},</p>
        
        <p>Welcome to the RFQ System! Your account has been created and you can now participate in the bidding process.</p>
        
        <div class="rfq-details">
            <h3>RFQ Details</h3>
            <p><strong>Title:</strong> {{ $rfq_title }}</p>
            <p><strong>Reference:</strong> {{ $rfq_reference }}</p>
            <p><strong>Bid Deadline:</strong> {{ $bid_deadline }}</p>
        </div>

        <p>You can now log in to your account and submit your bid for this RFQ:</p>
        
        <a href="{{ $rfq_url }}" class="button">Submit Your Bid</a>
        
        <p>If you need to log in first, you can use the login page:</p>
        
        <a href="{{ $login_url }}" class="button">Login to Account</a>
        
        <p>If you have any questions about this RFQ or need assistance, please contact the RFQ creator directly.</p>
        
        <p>Best regards,<br>
        RFQ System Team</p>
    </div>

    <div class="footer">
        <p>This is an automated message from the RFQ System. Please do not reply to this email.</p>
        <p>If you believe you received this email in error, please contact the system administrator.</p>
    </div>
</body>
</html>
