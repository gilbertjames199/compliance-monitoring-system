<?php

namespace App\Models;

use App\Models\Office;
use App\Models\RequiredDocument;
use App\Traits\HasAuditLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ComplyingOffice extends Model
{
    use HasAuditLog;
    
    protected $connection = 'mysql';
    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
    ];

    // Add this to store snapshot data before deletion
    protected $snapshotData = [];

    public function setSnapshotData(array $data): void
    {
        $this->snapshotData = $data;
    }

    public function getSnapshotData(): array
    {
        return $this->snapshotData;
    }

    protected static function booted(): void
    {
        static::updating(function ($model) {
            // Only notify when status explicitly changes TO "Complied" (1)
            $statusChanged = $model->isDirty('status') && (int) $model->status === 1;

            if (!$statusChanged) {
                return;
            }

            $requiredDocument = RequiredDocument::find($model->required_document_id);

            if (!$requiredDocument) {
                return;
            }

            $agencyDepartmentCode = Office::on('mysql2')
                ->where('office', $requiredDocument->agency_name)
                ->value('department_code');

            if (!$agencyDepartmentCode) {
                return;
            }

            $agencyUserIds = DB::connection('mysql2')
                ->table('systemusers')
                ->where('department_code', $agencyDepartmentCode)
                ->pluck('recid');

            $complyingOfficeName = Office::on('mysql2')
                ->where('department_code', $model->department_code)
                ->value('office') ?? 'An office';

            $agencyUserIds->each(function ($userId) use ($requiredDocument, $complyingOfficeName) {
                $recipient = User::find($userId);

                if (!$recipient) {
                    return;
                }

                // Only notify users who are authorized to update RequiredDocuments
                if (!$recipient->can('Update:RequiredDocument')) {
                    return;
                }

                Notification::make()
                    ->title('Compliance Submission Update')
                    ->body("{$complyingOfficeName} marked their compliance as Complied for: {$requiredDocument->requirement}")
                    ->icon('heroicon-o-document-check')
                    ->iconColor('success')
                    ->actions([
                        Action::make('view')
                            ->label('View Submission')
                            ->url(url("/admin/required-documents/{$requiredDocument->id}/edit"))
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($recipient);

                // Atomically tag the just-sent notification using JSON_SET
                // Avoids race conditions from fetch-then-update
                \Illuminate\Notifications\DatabaseNotification::query()
                    ->where('notifiable_type', \App\Models\User::class)
                    ->where('notifiable_id', $recipient->getKey())
                    ->orderByDesc('created_at')
                    ->limit(1)
                    ->update([
                        'data' => \Illuminate\Support\Facades\DB::raw(
                            "JSON_SET(data, '$.required_document_id', {$requiredDocument->id})"
                        )
                    ]);
        });
        });

        // CRITICAL: Capture snapshot BEFORE deletion
        static::deleting(function ($model) {
            // Load all necessary relationships while they're still available
            $model->loadMissing(['requiredDocument', 'office']);
           
            // Get FMS office name as a clean string
            $fmsOfficeName = 'Unknown FMS Office';
            if ($model->requiredDocument && $model->requiredDocument->agency_name) {
                $officeModel = Office::on('mysql2')
                    ->where('office', $model->requiredDocument->agency_name)
                    ->first();
               
                if ($officeModel) {
                    $fmsOfficeName = is_string($officeModel->office)
                        ? $officeModel->office
                        : (string) $officeModel->office;
                }
            }
           
            // Get the complying office name as string
            $complyingOfficeName = 'Unknown Office';
            if ($model->office) {
                $complyingOfficeName = is_string($model->office->office)
                    ? $model->office->office
                    : (string) $model->office->office;
            }
           
            // CRITICAL: Capture requirement_id BEFORE it's lost
            $requirementId = $model->requirement_id ?? $model->required_document_id;
           
            $model->setSnapshotData([
                'status'                => $model->status,
                'validation_status'     => $model->validation_status,
                'fms_office_name'       => $fmsOfficeName,
                'agency_name'           => $model->requiredDocument?->agency_name ?? 'N/A',
                'requirement_name'      => $model->requiredDocument?->requirement ?? 'Deleted Requirement',
                'complying_office_name' => $complyingOfficeName,
                'admin_remarks'         => $model->admin_remarks,
                'required_document_id'  => $requirementId,
                'requirement_id'        => $requirementId,
            ]);
        });
    }

    public function getRequirementTitleAttribute(): string
    {
        return $this->requiredDocument?->requirement ?? (string) $this->required_document_id;
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'department_code', 'department_code');
    }

    public function requiredDocument()
    {
        return $this->belongsTo(RequiredDocument::class, 'required_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}