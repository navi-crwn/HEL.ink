<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the address an account used to have, when the address is changed: if it was not the owner, the
 * old inbox is where they find out. Short, in the account's language, with the new address masked, and
 * with no link that signs anyone in.
 */
class EmailChangedNotice extends Mailable
{
    use Queueable, SerializesModels;

    public string $maskedEmail;

    public function __construct(public string $name, string $newEmail, ?string $locale = null)
    {
        $this->maskedEmail = self::mask($newEmail);
        if ($locale) {
            $this->locale($locale);
        }
    }

    /** "jane@example.com" becomes "j•••@e•••.com": the first letter of each part and the last label of the domain. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $labels = explode('.', $domain);
        $tld = count($labels) > 1 ? '.'.end($labels) : '';

        return mb_substr($local, 0, 1).'•••@'.mb_substr($domain, 0, 1).'•••'.$tld;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('The email address of your :app account was changed', ['app' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.email-changed');
    }
}
