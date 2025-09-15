<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RfqInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $rfq;
    public $supplier;
    public $buyer;
    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct($rfq, $supplier, $buyer, $data = [])
    {
        $this->rfq = $rfq;
        $this->supplier = $supplier;
        $this->buyer = $buyer;
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'RFQ Invitation: ' . $this->rfq->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.rfq-invitation',
            with: [
                'rfq' => $this->rfq,
                'supplier' => $this->supplier,
                'buyer' => $this->buyer,
                'data' => $this->data,
            ],
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
}
