<?php

namespace App\Filament\Resources\ComplyingOffices\Pages;

use App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource;
use Filament\Actions\DeleteAction;
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        $record = $this->record;

        // Only mutate if attachments field was explicitly submitted
        if (!array_key_exists('attachments', $data)) {
            return $data;
        }

        // Check if attachments are empty
        $attachments = $data['attachments'] ?? null;
        $isEmpty = empty($attachments) || (is_array($attachments) && count(array_filter($attachments)) === 0);
    
        if ($isEmpty) {
            // 🚫 No files → reset to not complied
            $data['status'] = -1;
            $data['submitted_at'] = null;
            $data['submitted_by'] = null; // clear previous submitter
            $data['validation_status'] = 'returned';
            $data['validated_at'] = null;
            $data['validated_by'] = null; // clear previous validator
        
        } else {
            // ✅ Files exist → set compliance based on role
            if ($user->hasRoleSafe('department_head') || $user->hasRoleSafe('super_admin')) {
                $data['status'] = 1; // Complied
            } else {
                $data['status'] = 0; // Partially complied
            }

            $data['submitted_by'] = $user->name;
            $data['submitted_at'] = now();

            $data['validation_status'] = 'pending_review';
            $data['validated_by'] = null;
            $data['validated_at'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Force Livewire to rehydrate form state
        $this->fillForm();
    }


    
}
