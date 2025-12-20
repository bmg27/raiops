<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant, public User $user)
    {
    }

    public function build()
    {
        return $this->subject('Welcome to RAI!')
            ->view('emails.tenant-welcome')
            ->with([
                'tenant' => $this->tenant,
                'user' => $this->user,
            ]);
    }
}

