<!DOCTYPE html>
<html>
<head>
    <title>Notification - RFQ System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 30px;
            border: 1px solid #ddd;
        }
        .header {
            background-color: #3B82F6;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 20px -30px;
        }
        .content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .notification-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        .type-rfq_created { background-color: #10B981; color: white; }
        .type-bid_submitted { background-color: #F59E0B; color: white; }
        .type-bid_awarded { background-color: #10B981; color: white; }
        .type-bid_rejected { background-color: #EF4444; color: white; }
        .type-po_created { background-color: #8B5CF6; color: white; }
        .type-user_registered { background-color: #06B6D4; color: white; }
        .type-supplier_approved { background-color: #10B981; color: white; }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3B82F6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 15px;
        }
        .button:hover {
            background-color: #2563EB;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .data-item {
            background-color: #f3f4f6;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .data-label {
            font-weight: bold;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>RFQ System Notification</h2>
        </div>
        
        <div class="content">
            <span class="notification-type type-{{ $notification->type }}">
                {{ ucfirst(str_replace('_', ' ', $notification->type)) }}
            </span>
            
            <h3>{{ $notification->title }}</h3>
            
            <p>{{ $notification->message }}</p>
            
            @if($notification->data && count($notification->data) > 0)
                <div style="margin-top: 20px;">
                    <h4>Details:</h4>
                    @foreach($notification->data as $key => $value)
                        <div class="data-item">
                            <span class="data-label">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                            {{ $value }}
                        </div>
                    @endforeach
                </div>
            @endif
            
            <div style="margin-top: 20px;">
                <a href="{{ url('/dashboard') }}" class="button">View in Dashboard</a>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated notification from the RFQ System.</p>
            <p>If you have any questions, please contact your system administrator.</p>
            <p>&copy; {{ date('Y') }} RFQ System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
