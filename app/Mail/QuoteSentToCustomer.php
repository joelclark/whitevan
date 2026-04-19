<?php

namespace App\Mail;

use App\Models\Estimate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteSentToCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Estimate $estimate) {}

    public function envelope(): Envelope
    {
        $accountName = $this->estimate->account->name;

        return new Envelope(
            subject: "Your quote from {$accountName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.quotes.sent',
            with: [
                'estimate' => $this->estimate,
                'customer' => $this->estimate->customer,
                'accountName' => $this->estimate->account->name,
                'approvalUrl' => route('approve.show', [
                    'approval_token' => $this->estimate->approval_token,
                ]),
            ],
        );
    }
}
