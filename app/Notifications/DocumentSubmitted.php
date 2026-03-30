<?php

namespace App\Notifications;

use App\Models\ComplyingOffice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentSubmitted extends Notification
{
    use Queueable;

    // ✅ Declare property with type
    protected ComplyingOffice $record;
    /**
     * Create a new notification instance.
     */
    public function __construct(ComplyingOffice $record)
    {
        $this->record = $record;
    }

    

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['mail', 'database']; // can be email, database, or others
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Document Submitted')
            ->line("The office {$this->record->department_name} has submitted the required document.")
            ->action('View Submission', url('/dashboard'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        return [
            'office_id' => $this->record->id,
            'status' => $this->record->status,
            'submitted_by' => $this->record->submitted_by,
        ];
    }
}
