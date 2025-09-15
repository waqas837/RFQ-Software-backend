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
            text-align: center;
            border-radius: 5px;
        }
        .content {
            padding: 20px;
            background-color: #ffffff;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Request for Quotation (RFQ)</h2>
        <p>You have been invited to submit a bid</p>
    </div>

    <div class="content">
        <p>Dear {{ $data['supplier_name'] ?? $supplier->name }},</p>

        <p>You have been invited to submit a bid for the following Request for Quotation:</p>

        <div class="details">
            <h3>{{ $rfq->title }}</h3>
            <p><strong>Description:</strong> {{ $rfq->description }}</p>
            <p><strong>Deadline:</strong> {{ $data['deadline'] ?? $rfq->deadline->format('F j, Y \a\t g:i A') }}</p>
            <p><strong>Buyer:</strong> {{ $data['buyer_name'] ?? $buyer->name }}</p>
        </div>

        <p>Please review the RFQ details and submit your bid before the deadline. You can access the full RFQ by clicking the button below:</p>

        <div style="text-align: center;">
            <a href="{{ $data['rfq_link'] ?? config('app.frontend_url') . '/rfqs/' . $rfq->id }}" class="button">
                View RFQ Details
            </a>
        </div>

        <p>If you have any questions about this RFQ, please contact the buyer at: <strong>{{ $data['contact_email'] ?? $buyer->email }}</strong></p>

        <p>Thank you for your interest in this opportunity.</p>

        <p>Best regards,<br>
        {{ $data['buyer_name'] ?? $buyer->name }}</p>
    </div>

    <div class="footer">
        <p>This is an automated message from the RFQ Management System.</p>
        <p>If you have any technical issues, please contact support.</p>
    </div>
</body>
</html>
