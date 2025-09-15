<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BidConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $bid;
    public $supplier;
    public $rfq;
    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct($bid, $supplier, $rfq, $data = [])
    {
        $this->bid = $bid;
        $this->supplier = $supplier;
        $this->rfq = $rfq;
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bid Confirmation: ' . $this->rfq->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bid-confirmation',
            with: [
                'bid' => $this->bid,
                'supplier' => $this->supplier,
                'rfq' => $this->rfq,
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
