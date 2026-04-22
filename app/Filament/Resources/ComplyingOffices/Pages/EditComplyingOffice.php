<?php

namespace App\Filament\Resources\ComplyingOffices\Pages;

use App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource;
use App\Models\Office;
use App\Support\FilamentAttachmentPreview;
use Filament\Resources\Pages\EditRecord;

class EditComplyingOffice extends EditRecord
{
    protected static string $resource = ComplyingOfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // DeleteAction::make(),
        ];
    }

    // protected function mutateFormDataBeforeSave(array $data): array
    // {
    //     $user = auth()->user();
    //     $record = $this->record;

    //     // Only mutate if attachments field was explicitly submitted
    //     if (!array_key_exists('attachments', $data)) {
    //         return $data;
    //     }

    //     // Check if attachments are empty
    //     $attachments = $data['attachments'] ?? null;
    //     $isEmpty = empty($attachments) || (is_array($attachments) && count(array_filter($attachments)) === 0);
    
    //     if ($isEmpty) {
    //         // 🚫 No files → reset to not complied
    //         $data['status'] = -1;
    //         $data['submitted_at'] = null;
    //         $data['submitted_by'] = null; // clear previous submitter
    //         $data['validation_status'] = 'returned';
    //         $data['validated_at'] = null;
    //         $data['validated_by'] = null; // clear previous validator
        
    //     } else {
    //         // ✅ Files exist → set compliance based on role
    //         if ($user->can('UpdateDepartmentComplianceStatus:ComplyingOffice') || $user->can('UpdateOwnOfficeComplianceStatus:ComplyingOffice')) {
    //             $data['status'] = 1; // Complied
    //         } else {
    //             //$data['status'] = 0; // Partially complied
    //             // Low-privileged user → NEVER downgrade an already-Complied record
    //             $data['status'] = ($record->status == 1) ? 1 : 0;
    //         }

    //         $data['submitted_by'] = $user->name;
    //         $data['submitted_at'] = now();

    //         $data['validation_status'] = 'pending_review';
    //         $data['validated_by'] = null;
    //         $data['validated_at'] = null;
    //     }

    //     return $data;
    // }
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        $record = $this->record->refresh(); // ← always read fresh from DB
        $drafts = $data['attachment_remark_drafts'] ?? [];
        $viewStates = $data['attachment_view_states'] ?? [];
        unset($data['attachment_remark_drafts']);

        // ✅ ALWAYS guard against stale-form status downgrade
        if (
            $record->status == 1 &&
            $record->validation_status !== 'returned' &&
            isset($data['status']) &&
            $data['status'] != 1
        ) {
            $data['status'] = 1;
        }

        if (!array_key_exists('attachments', $data)) {
            return $data;
        }

        $attachments = $data['attachments'] ?? null;
        $isEmpty = empty($attachments) || (is_array($attachments) && count(array_filter($attachments)) === 0);

        if ($isEmpty) {
            $data['status']            = -1;
            $data['submitted_at']      = null;
            $data['submitted_by']      = null;
            $data['validation_status'] = 'returned';
            $data['validated_at']      = null;
            $data['validated_by']      = null;
            $data['attachment_remarks'] = [];
            $data['attachment_annotations'] = [];
            $data['attachment_view_states'] = [];

        } else {
            $alreadyComplied = $record->status == 1 && $record->validation_status !== 'returned';

            if ($alreadyComplied) {
                // ✅ Preserve ALL original DB values — nothing should change
                $data['status']            = 1;
                $data['submitted_by']      = $record->submitted_by;
                $data['submitted_at']      = $record->submitted_at;
                $data['validation_status'] = $record->validation_status;
                $data['validated_by']      = $record->validated_by;
                $data['validated_at']      = $record->validated_at;
                $data['attachment_remarks'] = $record->attachment_remarks;
                $data['attachment_annotations'] = $record->attachment_annotations;
                $data['attachment_view_states'] = $record->attachment_view_states;

            } else {
                // Fresh submission or re-submission after return
                if (
                    $user->can('UpdateDepartmentComplianceStatus:ComplyingOffice') ||
                    $user->can('UpdateOwnOfficeComplianceStatus:ComplyingOffice')
                ) {
                    $data['status'] = 1;
                } else {
                    $data['status'] = ($record->status == 1) ? 1 : 0;
                }

                $data['submitted_by']      = $user->name;
                $data['submitted_at']      = now();
                $data['validation_status'] = 'pending_review';
                $data['validated_by']      = null;
                $data['validated_at']      = null;
            }

            $data['attachment_remarks'] = FilamentAttachmentPreview::mergeRemarkThreads(
                $record->attachment_remarks,
                $drafts,
                $user->name,
                $this->resolveAttachmentCommentAuthorLabel($record),
                $this->resolveAttachmentCommentAuthorType($record)
            );

            $data['attachment_annotations'] = $record->attachment_annotations;
            $data['attachment_view_states'] = FilamentAttachmentPreview::filterViewStatesToFiles(
                $viewStates,
                FilamentAttachmentPreview::payload($attachments)['files']
            );
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Force Livewire to rehydrate form state
        $this->fillForm();
        $this->dispatch('refresh-form');
    }

    protected function resolveAttachmentCommentAuthorLabel($record): string
    {
        $user = auth()->user();
        $shortName = Office::where('department_code', $user->department_code)->value('short_name');

        if (filled($shortName)) {
            return (string) $shortName;
        }

        return (string) ($user->department_code ?? 'User');
    }

    protected function resolveAttachmentCommentAuthorType($record): string
    {
        $user = auth()->user();

        if ($user->hasRoleSafe('super_admin')) {
            return 'super_admin';
        }

        if ($record && $user->department_code === $record->department_code) {
            return 'complying_office';
        }

        return 'user';
    }


    
}
