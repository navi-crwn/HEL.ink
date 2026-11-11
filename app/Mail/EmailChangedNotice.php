<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the address an account used to have, when the address is changed: if it was not the owner, the
 * old inbox is where they find out. Plain and short; it holds no link that signs anyone in.
 */
class EmailChangedNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $newEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'The email address of your '.config('app.name').' account was changed');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.email-changed');
    }
}
