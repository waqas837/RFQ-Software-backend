<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RFQ Invitation</title>
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
        <h1>RFQ Invitation</h1>
        <p>You have been invited to participate in a new Request for Quotation (RFQ)</p>
    </div>

    <div class="content">
        <p>Hello {{ $recipientName }},</p>
        
        <p>You have been invited to participate in the following RFQ:</p>
        
        <div class="rfq-details">
            <h3>{{ $rfq->title }}</h3>
            <p><strong>Reference:</strong> {{ $rfq->reference_number }}</p>
            <p><strong>Description:</strong> {{ $rfq->description }}</p>
            <p><strong>Bid Deadline:</strong> {{ \Carbon\Carbon::parse($rfq->bid_deadline)->format('M d, Y') }}</p>
            <p><strong>Delivery Date:</strong> {{ \Carbon\Carbon::parse($rfq->delivery_date)->format('M d, Y') }}</p>
            @if($rfq->budget_min || $rfq->budget_max)
                <p><strong>Budget Range:</strong> {{ $rfq->formatted_budget }}</p>
            @endif
        </div>

        <p>To view the complete RFQ details and submit your quotation, please click the button below:</p>
        
        <a href="{{ $rfqUrl }}" class="button">View RFQ Details</a>
        
        @if(!$user)
            <p><strong>Note:</strong> You are receiving this invitation as an external contact. To participate in the bidding process, you may need to create an account or contact the RFQ creator directly.</p>
        @endif
        
        <p>If you have any questions about this RFQ, please contact the RFQ creator directly.</p>
        
        <p>Best regards,<br>
        RFQ System</p>
    </div>

    <div class="footer">
        <p>This is an automated message from the RFQ System. Please do not reply to this email.</p>
        <p>If you believe you received this email in error, please contact the system administrator.</p>
    </div>
</body>
</html>