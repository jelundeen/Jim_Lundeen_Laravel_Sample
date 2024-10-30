<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CreatePassword extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, $create_password_link)
    {
        $this->user = $user;
        $this->create_password_link = $create_password_link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject('Welcome to the Xfinity Stream Content Provider Portal (CPP)')
            ->view('emails.welcome')
            ->with([
                'name' => $this->user->name,
                'create_password_link' => $this->create_password_link,
            ]);
    }
}
