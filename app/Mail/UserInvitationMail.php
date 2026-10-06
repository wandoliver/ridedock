<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class UserInvitationMail extends Mailable
{
    public function __construct(public readonly string $activationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('accounts.mail_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.user-invitation');
    }
}
