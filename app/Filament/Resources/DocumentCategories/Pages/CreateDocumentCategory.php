<?php

namespace App\Filament\Resources\DocumentCategories\Pages;

use App\Filament\Resources\DocumentCategories\DocumentCategoryResource;
use App\Filament\Resources\DocumentCategories\Schemas\DocumentCategoryForm;
use App\Models\ComplyingOffice;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateDocumentCategory extends CreateRecord
{
    protected static string $resource = DocumentCategoryResource::class;
// dd(Arr::get($data));

        // dd($this->form->getState());
        // dd(json_encode($data['RequiredDocuments'], JSON_PRETTY_PRINT));
    // protected function mutateFormDataBeforeCreate(array $data): array
    // {

    //     if (! empty($data['requiredDocuments'])) {
    //         dd("not empty");
    //         foreach ($data['requiredDocuments'] as $i => $doc) {
    //             $selected = $doc['selected_offices'] ?? [];
    //             $status   = $doc['status'] ?? 'Pending';

    //             // ✅ Convert to relationship data for ComplyingOffice
    //             $data['requiredDocuments'][$i]['complyingOffices'] = collect($selected)->map(fn ($deptCode) => [
    //                 'department_code' => $deptCode,
    //                 'status' => $status,
    //             ])->toArray();

    //             // ✅ Remove transient fields
    //             unset($data['requiredDocuments'][$i]['selected_offices'], $data['requiredDocuments'][$i]['status']);
    //         }
    //     }else{
    //         dd("empty dataset");
    //     }

    //     return $data;
    // }

    // protected function mutateFormDataBeforeFill(array $data): array
    // {
    //     if (!empty($data['requiredDocuments'])) {
    //         dd("not empty");
    //         foreach ($data['requiredDocuments'] as $i => $doc) {
    //             $selected = $doc['selected_offices'] ?? [];
    //             $status = $doc['status'] ?? 'Pending';

    //             // Set complyingOffices for display
    //             $data['requiredDocuments'][$i]['complyingOffices'] = collect($selected)->map(fn ($deptCode) => [
    //                 'department_code' => $deptCode,
    //                 'status' => $status,
    //             ])->toArray();
    //         }
    //     }else{
    //         dd("empty dataset");
    //     }

    //     return $data;
    // }
    // protected function mutateFormDataBeforeSave(array $data): array
    // {
    //     if (! empty($data['requiredDocuments'])) {
    //         dd("not empty");
    //         foreach ($data['requiredDocuments'] as $i => $doc) {
    //             $selected = $doc['selected_offices'] ?? [];
    //             $status   = $doc['status'] ?? 'Pending';

    //             // ✅ Convert to relationship data for ComplyingOffice
    //             $data['requiredDocuments'][$i]['complyingOffices'] = collect($selected)->map(fn ($deptCode) => [
    //                 'department_code' => $deptCode,
    //                 'status' => $status,
    //             ])->toArray();

    //             // ✅ Remove transient fields
    //             unset($data['requiredDocuments'][$i]['selected_offices'], $data['requiredDocuments'][$i]['status']);
    //         }
    //     }else{
    //         dd("empty dataset");
    //     }

    //     return $data;
    // }
    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     $data['category'] = auth()->user()->id();
    //     dd($data, auth()->user());
    //     return $data;
    // }
    // protected function getCreatedNotification(): ?Notification
    // {
    //     return Notification::make()
    //         ->success()
    //         ->title('User registered')
    //         ->body('The user has been created successfully.');
    // }
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
        return $data;
    }

    protected function afterCreate(): void
    {
        // dd($this->record, $this->data);
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

}
