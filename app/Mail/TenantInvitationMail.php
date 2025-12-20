<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TenantInvitation $invitation)
    {
    }

    public function build()
    {
        return $this->subject('Invitation to Join RAI')
            ->view('emails.tenant-invitation')
            ->with([
                'invitation' => $this->invitation,
                'url' => $this->invitation->getInvitationUrl(),
            ]);
    }
}

