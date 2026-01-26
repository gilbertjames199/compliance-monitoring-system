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
            DeleteAction::make()
                ->visible(function ($record) {
                    if (!$record) {
                        return true; // Allow viewing during creation
                    }
                    
                    $user = auth()->user();
                    $userOfficeName = optional($user->office)->office;
                    
                    // Show only if user is from the requiring agency
                    return $userOfficeName === $record->agency_name;
                }),
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

    protected function afterSave(): void
    {
        $selected = $this->form->getState()['complying_offices'] ?? [];
        
        if (empty($selected)) {
            return; // Don't do anything if no offices selected
        }

        // Get existing complying offices
        $existing = $this->record->complyingOffices()
            ->pluck('department_code')
            ->toArray();

        // Find offices to add (selected but not existing)
        $toAdd = array_diff($selected, $existing);

        // Find offices to remove (existing but not selected)
        $toRemove = array_diff($existing, $selected);

        // Remove unselected offices
        if (!empty($toRemove)) {
            $this->record->complyingOffices()
                ->whereIn('department_code', $toRemove)
                ->delete();
        }

        // Add new offices (only create new records, don't touch existing ones)
        foreach ($toAdd as $deptCode) {
            $this->record->complyingOffices()->create([
                'department_code' => $deptCode,
                'status' => '-1',
                'validation_status' => 'pending_review',
                
            ]);
        }
    }



}
