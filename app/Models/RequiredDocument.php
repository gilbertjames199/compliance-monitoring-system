<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        return $this->hasMany(ComplyingOffice::class, 'requirement_id', 'id');
    }

    public function requiringAgency()
    {
        return $this->belongsTo(Office::class, 'requiring_agency_id');
    }

    // app/Models/RequiredDocument.php

    protected static function booted()
    {
        // static::created(function ($requiredDocument) {
        //     foreach ($requiredDocument->complyingOffices as $office) {
        //         $users = \App\Models\User::where('department_code', $office->department_code)->get();
        //         foreach ($users as $user) {
        //             \Illuminate\Support\Facades\Mail::to($user->email)
        //                 ->queue(new \App\Mail\RequirementDeadlineMail($requiredDocument));
        //         }
        //     }
        // });

        static::created(function ($requiredDocument) {
            foreach ($requiredDocument->complyingOffices as $office) {
                $query = \App\Models\User::where('department_code', $office->department_code);
                
                // If confidential, only notify super_admin and dept head

                if ($requiredDocument->is_confidential) {
                    $query->whereIn('role', ['super_admin', 'department_head']);
                }
                
                $users = $query->get();
                
                foreach ($users as $user) {
                    \Illuminate\Support\Facades\Mail::to($user->email)
                        ->queue(new \App\Mail\RequirementDeadlineMail($requiredDocument));
                }
            }
        });
    }

}
