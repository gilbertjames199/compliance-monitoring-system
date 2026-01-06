<?php

namespace App\Notifications;

use App\Models\RequiredDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequiredDocumentCreatedNotification extends Notification
{
    use Queueable;

    protected $requirement;

    public function __construct(RequiredDocument $requirement)
    {
        $this->requirement = $requirement;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['mail', 'database']; // email + in-app notification
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Requirement Created: ' . $this->requirement->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new requirement has been created that requires your office\'s compliance.')
            ->line('**Requirement:** ' . $this->requirement->title)
            ->line('**Description:** ' . $this->requirement->description)
            ->line('**Due Date:** ' . $this->requirement->due_date?->format('F d, Y'))
            ->action('View Requirement', url('/admin/requirements/' . $this->requirement->id))
            ->line('Please review and take necessary action.');
    }
    
    public function toDatabase($notifiable)
    {
        return [
            'requirement_id' => $this->requirement->id,
            'requirement_title' => $this->requirement->title,
            'message' => 'New requirement created for your office',
        ];
    }


    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
