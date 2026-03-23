<?php

namespace App\Models;

use App\Models\Office;
use App\Models\RequiredDocument;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ComplyingOffice extends Model
{
    protected $connection = 'mysql';
    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function ($model) {
            $statusChanged = $model->isDirty('status') && in_array($model->status, [0, 1]);
            $attachmentsChanged = $model->isDirty('attachments');

            if (!$statusChanged && !$attachmentsChanged) {
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

            $statusLabel = match((int) $model->status) {
                0  => 'Partially Complied',
                1  => 'Complied',
                default => 'Updated',
            };

            $agencyUserIds->each(function ($userId) use ($requiredDocument, $complyingOfficeName, $statusLabel, $model) {
                $recipient = User::find($userId);

                if (!$recipient) {
                    return;
                }

                Notification::make()
                    ->title('Compliance Submission Update')
                    ->body("{$complyingOfficeName} marked their compliance as {$statusLabel} for: {$requiredDocument->requirement}")
                    ->icon('heroicon-o-document-check')
                    ->iconColor('success')
                    ->actions([
                        Action::make('view')
                            ->label('View Submission')
                            //->url(route('filament.admin.resources.required-documents.edit', ['record' => $requiredDocument->id]))
                            ->url(url("/required-documents/{$requiredDocument->id}/edit"))
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($recipient);
            });
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
