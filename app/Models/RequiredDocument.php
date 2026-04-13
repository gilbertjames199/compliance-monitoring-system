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
            \Illuminate\Support\Facades\DB::connection('mysql')
                ->table('notifications')
                ->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(data, '$.required_document_id')) = ?",
                    [(string) $document->id]
                )
                ->delete();
        });
        // Note: We CANNOT send email notifications here because 
        // complyingOffices are created AFTER this model is saved
        // The email notifications should be sent in the Resource's afterCreate() method
    }

}
