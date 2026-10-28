<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ServiceAlert extends Mailable
{
    public function __construct(public string $subjectLine, public string $text) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[HOP watchdog] '.$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.service-alert');
    }
}
