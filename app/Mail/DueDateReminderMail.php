<?php

namespace App\Mail;

use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DueDateReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public RequiredDocument $requirement;
    public User $user;
    public string $office;

    /**
     * Create a new message instance.
     */
    // public function __construct(RequiredDocument $requirement)
    // {
    //     $this->requirement = $requirement;
    // }
    public function __construct(RequiredDocument $requirement, User $user, string $office)
    {
        $this->requirement = $requirement;
        $this->user = $user;
        $this->office = $office;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // return $this->subject('Due Date Reminder: ' . $this->requirement->requirement)
        //             ->view('emails.due_date_reminder', ['document' => $this->requirement]);
        return $this->subject('Due Date Reminder: ' . $this->requirement->requirement)
                ->view('emails.due_date_reminder')
                ->with([
                    'requirement' => $this->requirement,
                    'user'        => $this->user,
                    'office'      => $this->office,
                ]);
    }
}
