<?php

namespace App\Notifications;

use App\Models\RequiredDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewRequirementAssigned extends Notification
{
    use Queueable;

    public $requirement;

    public function __construct(RequiredDocument $requirement)
    {
        $this->requirement = $requirement;
    }

    public function via($notifiable)
    {
        return ['database']; // 🔔 Filament bell only
    }

    public function toArray($notifiable)
    {
        return [
            'requirement_id' => $this->requirement->id,
            'title'          => $this->requirement->title,
            'deadline'       => $this->requirement->deadline,
            'message'        => 'A new requirement has been assigned to your office.',
        ];
    }
}
