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
        if (! empty($data['attachments'])) {

            $user = auth()->user();

            // Compliance status
            if ($user->hasRole('department_head')) {
                $data['status'] = 1; // complied
            } else {
                $data['status'] = 0; // partial
            }

            // 🔥 returned → pending_review
            $data['validation_status'] = 'pending_review';

            $data['submitted_at'] = now();
            $data['validated_at'] = null;
            
        }

        return $data;
    }

    
}
