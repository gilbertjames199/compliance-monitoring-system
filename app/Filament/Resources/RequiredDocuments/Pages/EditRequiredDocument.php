<?php

namespace App\Filament\Resources\RequiredDocuments\Pages;

use App\Filament\Resources\RequiredDocuments\RequiredDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequiredDocument extends EditRecord
{
    protected static string $resource = RequiredDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // dd($data);

        $state = $this->form->getState();
        // if (!empty($state['requiredDocuments'][0])) {

        //     dd($state['requiredDocuments']); // ✅ works!
        // } else {
        //     dd('empty dataset mutateFormDataBeforeSave');
        // }
        // dd(isset($data['requiredDocuments']) , is_array($data['requiredDocuments']) , count($data['requiredDocuments']));
        if (! empty($data['requiredDocuments'])) {
            // dd("not empty");
            foreach ($data['requiredDocuments'] as $i => $doc) {
                $selected = $doc['selected_offices'] ?? [];
                $status   = $doc['status'] ?? 'Pending';

                // ✅ Convert to relationship data for ComplyingOffice
                $data['requiredDocuments'][$i]['complyingOffices'] = collect($selected)->map(fn ($deptCode) => [
                    'department_code' => $deptCode,
                    'status' => $status,
                ])->toArray();

                // ✅ Remove transient fields
                unset($data['requiredDocuments'][$i]['selected_offices'], $data['requiredDocuments'][$i]['status']);
            }
        }else{
            // dd("empty dataset");
        }

        return $data;
    }


}
