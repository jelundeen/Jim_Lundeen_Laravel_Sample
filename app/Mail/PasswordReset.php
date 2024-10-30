<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, $reset_link)
    {
        $this->user = $user;
        $this->reset_link = $reset_link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject('Xfinity Stream Content Provider Portal (CPP) Password Reset Request')
            ->view('emails.password-reset')
            ->with([
                'name' => $this->user->name,
                'reset_link' => $this->reset_link,
            ]);
    }
}
