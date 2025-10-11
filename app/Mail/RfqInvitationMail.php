<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Rfq;
use App\Models\User;

class RfqInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $rfq;
    public $user;
    public $externalEmail;
    public $invitation;

    /**
     * Create a new message instance.
     */
    public function __construct(Rfq $rfq, ?User $user = null, ?string $externalEmail = null, $invitation = null)
    {
        $this->rfq = $rfq;
        $this->user = $user;
        $this->externalEmail = $externalEmail;
        $this->invitation = $invitation;
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
                'user' => $this->user,
                'externalEmail' => $this->externalEmail,
                'invitation' => $this->invitation,
                'recipientName' => $this->user ? $this->user->name : 'Valued Partner',
                'loginUrl' => config('app.frontend_url') . '/login',
                'rfqUrl' => config('app.frontend_url') . '/rfqs/' . $this->rfq->id,
                'registrationUrl' => $this->invitation ? config('app.frontend_url') . '/invitation?token=' . $this->invitation->token : null,
                'formattedBudget' => $this->rfq->formatted_budget,
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
}
