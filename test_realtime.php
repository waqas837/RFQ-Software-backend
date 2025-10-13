<?php

require_once 'vendor/autoload.php';

use App\Models\Negotiation;
use App\Models\NegotiationMessage;
use App\Events\NegotiationMessageSent;

// Test real-time negotiation system
echo "Testing Real-time Negotiation System...\n";

// Create a test negotiation
$negotiation = Negotiation::first();
if (!$negotiation) {
    echo "No negotiations found. Please create a negotiation first.\n";
    exit;
}

echo "Found negotiation ID: {$negotiation->id}\n";

// Create a test message
$message = NegotiationMessage::create([
    'negotiation_id' => $negotiation->id,
    'sender_id' => $negotiation->initiated_by,
    'message' => 'Test real-time message',
    'message_type' => 'text',
    'is_read' => false,
]);

echo "Created test message ID: {$message->id}\n";

// Test broadcasting
try {
    broadcast(new NegotiationMessageSent($message));
    echo "✅ Broadcasting test successful!\n";
} catch (Exception $e) {
    echo "❌ Broadcasting test failed: " . $e->getMessage() . "\n";
}

echo "Real-time system test completed.\n";
