<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $purchaseOrder;
    public $status;
    public $recipientType;

    /**
     * Create a new message instance.
     */
    public function __construct(PurchaseOrder $purchaseOrder, string $status, string $recipientType = 'supplier')
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->status = $status;
        $this->recipientType = $recipientType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->getSubject();
        
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-order-notification',
            with: [
                'purchaseOrder' => $this->purchaseOrder,
                'status' => $this->status,
                'recipientType' => $this->recipientType,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get email subject based on status and recipient type
     */
    private function getSubject(): string
    {
        $poNumber = $this->purchaseOrder->po_number;
        
        switch ($this->status) {
            case 'sent_to_supplier':
                return "New Purchase Order Received - {$poNumber}";
            case 'in_progress':
                return "Purchase Order In Progress - {$poNumber}";
            case 'delivered':
                return "Purchase Order Delivered - {$poNumber}";
            default:
                return "Purchase Order Update - {$poNumber}";
        }
    }
}
