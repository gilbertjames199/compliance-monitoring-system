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

        // Check if attachments are empty
        $attachments = $data['attachments'] ?? null;
        $isEmpty = empty($attachments) || (is_array($attachments) && count(array_filter($attachments)) === 0);
    
       if ($isEmpty) {
            // 🚫 No files → reset to not complied
            $data['status'] = -1;
            $data['submitted_at'] = null;
            $data['validation_status'] = 'returned';
            $data['validated_at'] = null;
       
        } else {
            // ✅ Files exist → set compliance based on role
            if ($user->hasRole('department_head')) {
                $data['status'] = 1; // Complied
            } else {
                $data['status'] = 0; // Partially complied
            }
            
            $data['validation_status'] = 'pending_review';
            $data['submitted_at'] = now();
            $data['validated_at'] = null;
         
        }

        return $data;
    }

    
}
