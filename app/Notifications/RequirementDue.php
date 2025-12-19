<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequirementDue extends Notification
{
    use Queueable;

    protected $requirement;

    /**
     * Create a new notification instance.
     */
    public function __construct($requirement)
    {
        $this->requirement = $requirement;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
     public function toMail(object $notifiable): MailMessage
    {
        $dueDate = $this->requirement->due_date->format('F d, Y');
        $daysLeft = now()->diffInDays($this->requirement->due_date, false);
        
        $subject = "Action Required: Document Requirement Due Soon";
        
        if ($daysLeft < 0) {
            $subject = "URGENT: Document Requirement is OVERDUE";
        } elseif ($daysLeft == 0) {
            $subject = "URGENT: Document Requirement is Due TODAY";
        }

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have a document requirement that needs attention.')
            ->line('**Requirement:** ' . $this->requirement->requirement)
            ->line('**Due Date:** ' . $dueDate)
            ->line('**Agency:** ' . $this->requirement->agency_name)
            ->line('**Category:** ' . $this->requirement->category->category)
            ->action('View Requirement', url('/requirements/' . $this->requirement->id))
            ->line('Please ensure this requirement is completed and submitted before the due date.')
            ->salutation('Regards, System Administrator');
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
            'requirement' => $this->requirement->requirement,
            'due_date' => $this->requirement->due_date,
            'agency' => $this->requirement->agency_name,
            'message' => 'A document requirement is due soon.',
        ];
    }
}
