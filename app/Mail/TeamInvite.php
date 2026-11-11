<?php

namespace App\Mail;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TeamInvitation $invitation,
        public Team $team,
        public User $inviter,
        public ?string $tempPassword = null,
        public bool $accountCreated = false,
    ) {}

    public function envelope(): Envelope
    {
        $subject = sprintf(
            "%s invited you to join %s on CsStrats",
            $this->inviter->name,
            $this->team->name,
        );
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $appUrl = rtrim(config('app.url'), '/');
        return new Content(
            view: 'mail.team-invite',
            with: [
                'invitation'     => $this->invitation,
                'team'           => $this->team,
                'inviter'        => $this->inviter,
                'tempPassword'   => $this->tempPassword,
                'accountCreated' => $this->accountCreated,
                'inviteUrl'      => $appUrl . '/team-invite/' . $this->invitation->token,
                'loginUrl'       => $appUrl . '/login',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
