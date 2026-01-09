<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\RequiredDocument;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RequiredDocumentCreatedNotification extends Notification
{
    use Queueable, HasRoles;

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
            // ->subject('New Requirement Created: ' . $this->requirement->title)
            ->subject('New Requirement Created: ' . $this->requirement->requirement)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new requirement has been created that requires your office\'s compliance.')
            ->line('Requirement: ' . $this->requirement->requirement)
            ->line('Agency: ' . $this->requirement->agency_name)
            // ->line('**Requirement:** ' . $this->requirement->title)
            ->line('**Description:** ' . $this->requirement->description)
            ->line('**Due Date:** ' . $this->requirement->due_date?->format('F d, Y'))
            ->action('View Requirement', url('/admin/requirements/' . $this->requirement->id))
            ->line('Please review and take necessary action.');
    }
    
    public function toDatabase($notifiable)
    {
        return [
            'requirement_id' => $this->requirement->id,
            'requirement_title' => $this->requirement->requirement,
            'agency_name' => $this->requirement->agency_name,
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
            'requirement_id' => $this->requirement->id,
            'requirement_title' => $this->requirement->requirement,
        ];
    }
}
