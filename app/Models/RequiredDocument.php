<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendRequirementNotification;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RequiredDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date',
        'date_from' => 'date',
        'is_confidential' => 'boolean',
        'is_recurring' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function complyingOffices()
    {
        return $this->hasMany(ComplyingOffice::class, 'required_document_id', 'id');
    }

    // public function complyingOffices(): BelongsToMany
    // {
    //     return $this->belongsToMany(Office::class, 'compliance_monitoring_db.complying_offices', 'department_code', 'required_document_id');
    // }

    public function requiringAgency()
    {
        return $this->belongsTo(Office::class, 'requiring_agency_id');
    }

    // public function

    // app/Models/RequiredDocument.php

    // protected static function booted()
    // {
    //     static::created(function ($requiredDocument) {
    //         foreach ($requiredDocument->complyingOffices as $office) {
    //             $query = \App\Models\User::where('department_code', $office->department_code);
                
    //             // If confidential, only notify super_admin and dept head

    //             if ($requiredDocument->is_confidential) {
    //                 $query->whereIn('role', ['super_admin', 'department_head']);
    //             }
                
    //             $users = $query->get();
                
    //             foreach ($users as $user) {
    //                 \Illuminate\Support\Facades\Mail::to($user->email)
    //                     ->queue(new \App\Mail\RequirementDeadlineMail($requiredDocument));
    //             }
    //         }

    //         static::created(function ($requirement) {
    //             // Dispatch the job to run 5 minutes later
    //             SendRequirementNotification::dispatch($requirement->id)->delay(now()->addMinutes(5));
    //         });
    //     });
    // }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_by ??= auth()->id();
        });

        static::created(function ($requiredDocument) {
            // Dispatch the job to run 5 minutes later
            SendRequirementNotification::dispatch($requiredDocument->id)->delay(now()->addMinutes(5));
        });

         static::deleting(function (RequiredDocument $document) {
        // Delete notifications where required_document_id matches
        \Illuminate\Notifications\DatabaseNotification::query()
            ->where('type', '!=', 'Filament\Notifications\DatabaseNotification') // Only our custom notifications
            ->whereRaw(
                "JSON_EXTRACT(data, '$.required_document_id') = ?",
                [(string) $document->id]
            )
            ->orWhereRaw(
                "JSON_EXTRACT(data, '$.required_document_id') = ?",
                [(int) $document->id]
            )
            ->delete();
            
        // Alternative approach: loop through and delete (more reliable)
        // $notifications = \Illuminate\Notifications\DatabaseNotification::query()
        //     ->get()
        //     ->filter(function ($notification) use ($document) {
        //         $data = $notification->data;
        //         return isset($data['required_document_id']) && 
        //                (string) $data['required_document_id'] === (string) $document->id;
        //     });
        //     
        // foreach ($notifications as $notification) {
        //     $notification->delete();
        // }
    });
        // Note: We CANNOT send email notifications here because 
        // complyingOffices are created AFTER this model is saved
        // The email notifications should be sent in the Resource's afterCreate() method
    }

}
