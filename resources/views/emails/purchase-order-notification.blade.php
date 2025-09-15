<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order Notification</title>
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
            background-color: #3B82F6;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }
        .status-sent { background-color: #8B5CF6; color: white; }
        .status-progress { background-color: #F59E0B; color: white; }
        .status-delivered { background-color: #10B981; color: white; }
        .po-details {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #3B82F6;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-label {
            font-weight: bold;
            color: #6b7280;
        }
        .detail-value {
            color: #111827;
        }
        .action-button {
            display: inline-block;
            background-color: #3B82F6;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Purchase Order Notification</h1>
        <p>RFQ Management System</p>
    </div>
    
    <div class="content">
        @if($recipientType === 'supplier')
            <h2>Hello {{ $purchaseOrder->supplierCompany->name ?? 'Supplier' }},</h2>
        @else
            <h2>Hello {{ $purchaseOrder->buyerCompany->name ?? 'Buyer' }},</h2>
        @endif

        @if($status === 'sent_to_supplier')
            <p>You have received a new Purchase Order that requires your attention.</p>
            <div class="status-badge status-sent">New Order Received</div>
        @elseif($status === 'in_progress')
            <p>The Purchase Order is now being fulfilled.</p>
            <div class="status-badge status-progress">In Progress</div>
        @elseif($status === 'delivered')
            <p>The Purchase Order has been successfully delivered.</p>
            <div class="status-badge status-delivered">Delivered</div>
        @endif

        <div class="po-details">
            <h3>Purchase Order Details</h3>
            <div class="detail-row">
                <span class="detail-label">PO Number:</span>
                <span class="detail-value">{{ $purchaseOrder->po_number }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">RFQ:</span>
                <span class="detail-value">{{ $purchaseOrder->rfq->title ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value">${{ number_format($purchaseOrder->total_amount, 2) }} {{ $purchaseOrder->currency }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order Date:</span>
                <span class="detail-value">{{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('M d, Y') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Expected Delivery:</span>
                <span class="detail-value">{{ $purchaseOrder->expected_delivery_date ? \Carbon\Carbon::parse($purchaseOrder->expected_delivery_date)->format('M d, Y') : 'N/A' }}</span>
            </div>
            @if($purchaseOrder->delivery_address)
            <div class="detail-row">
                <span class="detail-label">Delivery Address:</span>
                <span class="detail-value">{{ $purchaseOrder->delivery_address }}</span>
            </div>
            @endif
            @if($purchaseOrder->payment_terms)
            <div class="detail-row">
                <span class="detail-label">Payment Terms:</span>
                <span class="detail-value">{{ $purchaseOrder->payment_terms }}</span>
            </div>
            @endif
        </div>

        @if($status === 'sent_to_supplier' && $recipientType === 'supplier')
            <p>Please review the order details and start fulfillment as soon as possible.</p>
            <a href="{{ config('app.frontend_url') }}/purchase-orders/{{ $purchaseOrder->id }}" class="action-button">
                View Purchase Order
            </a>
        @elseif($status === 'in_progress' && $recipientType === 'buyer')
            <p>The supplier has started fulfilling your order. You will be notified when it's delivered.</p>
        @elseif($status === 'delivered' && $recipientType === 'buyer')
            <p>Your order has been successfully delivered. Please confirm receipt and quality.</p>
        @endif

        @if($purchaseOrder->notes)
        <div style="background-color: #fef3c7; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <h4 style="margin: 0 0 10px 0; color: #92400e;">Additional Notes:</h4>
            <p style="margin: 0; color: #92400e;">{{ $purchaseOrder->notes }}</p>
        </div>
        @endif
    </div>

    <div class="footer">
        <p>This is an automated notification from the RFQ Management System.</p>
        <p>Please do not reply to this email.</p>
    </div>
</body>
</html>
