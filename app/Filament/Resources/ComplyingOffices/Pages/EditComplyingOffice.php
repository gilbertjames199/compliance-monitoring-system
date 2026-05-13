<?php

namespace App\Filament\Resources\ComplyingOffices\Pages;

use App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource;
use App\Models\Office;
use App\Support\FilamentAttachmentPreview;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;

class EditComplyingOffice extends EditRecord
{
    protected static string $resource = ComplyingOfficeResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        $user = auth()->user();
        $hasApprovalPermission = $user->can('UpdateDepartmentComplianceStatus:ComplyingOffice');

        $action = Action::make('save')
            ->label('Submit')
            ->action(fn () => $this->save())
            ->keyBindings(['mod+s'])
            ->color('primary');

        // If user has approval permission, show the completion modal
        if ($hasApprovalPermission) {
            $action
                ->requiresConfirmation(fn (): bool => $this->shouldConfirmSubmit())
                ->disabled(fn () => (int) $this->record->status === 1)
                ->modalIcon('heroicon-o-paper-airplane')
                ->modalIconColor('primary')
                ->modalHeading('Are you sure you want to submit this?')
                ->modalDescription(
                    str('**Once submitted, you will no longer be able to modify the uploaded files** unless the validation status is returned by the requiring agency.')
                        ->markdown()
                        ->toHtmlString()
                )
                ->modalSubmitActionLabel('Submit Now')
                ->modalCancelActionLabel('Go Back & Review')
                ->modalWidth('lg')
                ->modalAlignment(Alignment::Center)
                ->modalFooterActionsAlignment(Alignment::Center);
        } else {
            // User without permission - ask for department head approval
            $action
                ->requiresConfirmation(true)
                ->disabled(fn () => (int) $this->record->status === 1)
                ->modalIcon('heroicon-o-exclamation-circle')
                ->modalIconColor('warning')
                ->modalHeading('Department Head Re-Submission Required')
->modalDescription(
    str('Your submission will be saved. To mark this compliance as **Complied**, the Department Head must click the **Submit** button again.

Please ensure the submission is reviewed and re-submitted once finalized.

If it remains **Partially Complied**, it will not be considered complete and cannot be reviewed by the requiring agency.')
        ->markdown()
        ->toHtmlString()
)
                ->modalSubmitActionLabel('Submit')
                ->modalCancelActionLabel('Go Back & Review')
                ->modalWidth('xl')
                ->modalAlignment(Alignment::Center)
                ->modalFooterActionsAlignment(Alignment::Center)
                ->after(function () {
                    // Optionally dispatch a notification to department head
                    $this->notifyDepartmentHead();
                });
        }

        return $action;
    }

    protected function notifyDepartmentHead(): void
    {
        $user = auth()->user();
        $record = $this->record;
        
        // Get department head users
        $headUsers = \App\Models\User::where('department_code', $user->department_code)
            ->role('department_head')
            ->get();

        foreach ($headUsers as $headUser) {
            \Filament\Notifications\Notification::make()
                ->title('Compliance Submission Awaiting Approval')
                ->body("{$user->name} has submitted a compliance document that requires your department head approval to mark as Complete.")
                ->icon('heroicon-o-document-check')
                ->iconColor('warning')
                ->actions([
                    \Filament\Actions\Action::make('view')
                        ->label('Review & Approve')
                        ->url(route('filament.admin.resources.complying-offices.edit', $record->id))
                        ->markAsRead(),
                ])
                ->sendToDatabase($headUser);
        }
    }

    protected function shouldConfirmSubmit(): bool
    {
        $user = auth()->user();
        
        // Only show confirmation modal for users with approval permission
        if (!$user->can('UpdateDepartmentComplianceStatus:ComplyingOffice')) {
            // For non-authorized users, always show the department head notification modal
            return true;
        }

        $data = $this->form->getState();
        $record = $this->record;

        $currentStatus = (int) ($record->status ?? -1);
        $newStatus = (int) ($data['status'] ?? $currentStatus);

        // Show confirmation only when marking as Complied (1)
        return $newStatus === 1 && $currentStatus !== 1;
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
        $annotationState = $data['attachment_annotations'] ?? $record->attachment_annotations;
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
                if ($user->can('UpdateDepartmentComplianceStatus:ComplyingOffice')) {
                    // Department head/admin: can set to Complied (1)
                    $data['status'] = 1;
                } else {
                    // Regular user: can only set to Partially Complied (0)
                    $data['status'] = 0;
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

            $data['attachment_annotations'] = FilamentAttachmentPreview::filterAnnotationsToFiles(
                $annotationState,
                FilamentAttachmentPreview::payload($attachments)['files']
            );
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
        $departmentCode = $record && $user->hasAccessToDepartment($record->department_code)
            ? $record->department_code
            : $user->department_code;
        $shortName = Office::where('department_code', $departmentCode)->value('short_name');

        if (filled($shortName)) {
            return (string) $shortName;
        }

        return (string) ($departmentCode ?? 'User');
    }

    protected function resolveAttachmentCommentAuthorType($record): string
    {
        $user = auth()->user();

        if ($user->hasRoleSafe('super_admin')) {
            return 'super_admin';
        }

        if ($record && $user->hasAccessToDepartment($record->department_code)) {
            return 'complying_office';
        }

        return 'user';
    }


    
}
