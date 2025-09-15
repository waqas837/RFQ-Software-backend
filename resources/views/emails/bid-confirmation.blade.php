<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bid Confirmation</title>
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
            background-color: #28a745;
            color: white;
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
        .confirmation-number {
            background-color: #28a745;
            color: white;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Bid Submission Confirmation</h2>
        <p>Your bid has been successfully submitted</p>
    </div>

    <div class="content">
        <p>Dear {{ $data['supplier_name'] ?? $supplier->name }},</p>

        <p>Thank you for submitting your bid. We have received your submission and it has been recorded in our system.</p>

        <div class="confirmation-number">
            Confirmation Number: {{ $data['confirmation_number'] ?? 'BID-' . str_pad($bid->id, 6, '0', STR_PAD_LEFT) }}
        </div>

        <div class="details">
            <h3>Bid Details</h3>
            <p><strong>RFQ Title:</strong> {{ $rfq->title }}</p>
            <p><strong>Bid Amount:</strong> {{ $data['bid_amount'] ?? '$' . number_format($bid->total_amount, 2) }}</p>
            <p><strong>Submission Date:</strong> {{ $data['submission_date'] ?? $bid->created_at->format('F j, Y \a\t g:i A') }}</p>
            <p><strong>RFQ Deadline:</strong> {{ $data['rfq_deadline'] ?? $rfq->deadline->format('F j, Y \a\t g:i A') }}</p>
        </div>

        <p>Your bid is now under review. We will notify you of the outcome once the evaluation process is complete.</p>

        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url') . '/bids/' . $bid->id }}" class="button">
                View Bid Details
            </a>
        </div>

        <p>If you need to make any changes to your bid, please contact us immediately. Note that changes may only be possible before the RFQ deadline.</p>

        <p>Thank you for your participation in this procurement process.</p>

        <p>Best regards,<br>
        Procurement Team</p>
    </div>

    <div class="footer">
        <p>This is an automated confirmation from the RFQ Management System.</p>
        <p>If you have any questions, please contact support.</p>
    </div>
</body>
</html>
