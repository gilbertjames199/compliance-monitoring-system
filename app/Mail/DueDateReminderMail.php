<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\RequiredDocument;

class DueDateReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public RequiredDocument $requirement;

    /**
     * Create a new message instance.
     */
    public function __construct(RequiredDocument $requirement)
    {
        $this->requirement = $requirement;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Due Date Reminder: ' . $this->requirement->requirement)
                    ->view('emails.due_date_reminder', ['document' => $this->requirement]);
    }
}
