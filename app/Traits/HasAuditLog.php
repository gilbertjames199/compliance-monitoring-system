<?php

namespace App\Traits;

use App\Models\AuditLog;

trait HasAuditLog
{
    // public static function bootHasAuditLog(): void
    // {
    //     static::updated(function ($model) {
    //         // Add this guard at the top:
    //         if (app()->runningInConsole() || $model->wasRecentlyCreated) {
    //             return;
    //         }

    //         AuditLog::create([
    //             'event'                  => 'updated',
    //             'user_id'                => $model->user_id ?? null,
    //             'acted_by'               => auth()->user()->recid ?? null,
    //             'action_at'              => now(),
    //             'requirement_id'         => $model->required_document_id ?? null,
    //             'complying_office_id'    => $model->id,
    //             'requiring_agency_name'  => $model->requiredDocument?->agency_name ?? null,
    //             'old_status'             => $model->getOriginal('status'),
    //             'old_validation_status'  => $model->getOriginal('validation_status'),
    //             'new_status'             => $model->status,
    //             'new_validation_status'  => $model->validation_status,
    //             'remarks'                => null,
    //         ]);
    //     });
    // }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'complying_office_id')->latest('action_at');
    }
}
