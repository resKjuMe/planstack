<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Einladung, sich zu registrieren und dabei automatisch einer Organisation
 * zugeordnet zu werden. Der Link enthält den Einladungscode der Organisation.
 */
class OrganizationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public User $inviter,
        public string $registerUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Einladung zu '.$this->organization->name.' auf Planstack',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.organization-invitation');
    }
}
