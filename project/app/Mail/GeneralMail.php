<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GeneralMail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailMessage; // Renamed to avoid conflict with reserved $message
    public $username;

    public $subject;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($message, $username, $subject)
    {
        $this->emailMessage = $message; // Store message content in $emailMessage
        $this->username = $username; // Store username in $username
        $this->subject = $subject; // Store subject in $subject
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject( $this->subject)
            ->view('email.general_message');
    }
}

