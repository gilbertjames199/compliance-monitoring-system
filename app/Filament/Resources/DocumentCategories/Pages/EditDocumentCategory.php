<?php

namespace App\Filament\Resources\DocumentCategories\Pages;

use App\Filament\Resources\DocumentCategories\DocumentCategoryResource;
use App\Models\ComplyingOffice;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentCategory extends EditRecord
{
    protected static string $resource = DocumentCategoryResource::class;

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

    protected function afterUpdate(): void
    {
        dd($this->record, $this->data, "after update");
        // The newly created Category record
        $category = $this->record;

        // These RequiredDocuments are already saved (hasMany relationship)
        $requiredDocuments = $this->data['requiredDocuments'] ?? [];
        // dd($requiredDocuments);
        foreach ($requiredDocuments as $uuid => $doc) {
            // Get the actual RequiredDocument that was just created under this category
            // You can match it by some unique field (requirement name + category, etc.)
            $requiredDocument = $category->requiredDocuments()
                ->where('requirement', $doc['requirement'])
                ->latest('id')
                ->first();

            if (! $requiredDocument) {
                continue; // Skip if not found
            }

            // Create ComplyingOffices for this already-existing RequiredDocument
            $complyingOffices = $doc['complying_offices'] ?? [];
            $status = $doc['status'] ?? -1;

            foreach ($complyingOffices as $departmentCode) {
                ComplyingOffice::create([
                    'department_code' => $departmentCode,
                    'requirement_id'  => $requiredDocument->id, // existing RequiredDocument ID
                    'status'          => $status,
                ]);
            }
        }
        // RequiredDocumentForm::afterCreate($this->record, $this->data);
        // return $this->data;

    }

    protected function afterSave(): void
{
    $category = $this->record;

    foreach ($this->data['requiredDocuments'] ?? [] as $doc) {

        $requiredDocument = $category->requiredDocuments()
            ->where('requirement', $doc['requirement'])
            ->first();

        if (! $requiredDocument) {
            continue;
        }

        // 🔥 IMPORTANT: delete old complying offices
        $requiredDocument->complyingOffices()->delete();

        // ✅ Insert updated ones
        foreach ($doc['complying_offices'] ?? [] as $departmentCode) {
            ComplyingOffice::create([
                'requirement_id'  => $requiredDocument->id,
                'department_code' => $departmentCode,
                'status'          => $doc['status'] ?? -1,
            ]);
        }
    }
}

}