<?php

namespace App\Filament\Resources\RequiredDocuments\Pages;

use App\Filament\Resources\RequiredDocuments\RequiredDocumentResource;
use App\Filament\Resources\RequiredDocuments\Schemas\RequiredDocumentForm;
use Filament\Resources\Pages\CreateRecord;

class CreateRequiredDocument extends CreateRecord
{
    protected static string $resource = RequiredDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // dd($data);
        // $selectedOffices = $data['_selected_offices'] ?? [];
        // $status = $data['_status'] ?? -1;

        // foreach ($selectedOffices as $deptCode) {
        //     ComplyingOffice::create([
        //         'department_code' => $deptCode,
        //         'requirement_id'  => $record->id,
        //         'status'          => $status,
        //     ]);
        // }
        return RequiredDocumentForm::mutateFormDataBeforeCreate($data);
    }

    protected function afterCreate(): void
    {
        dd($this->record, $this->data);
        RequiredDocumentForm::afterCreate($this->record, $this->data);
    }
}
