<?php

namespace App\Filament\Resources\RequiredDocuments\Pages;

use App\Models\User;
use Filament\Actions\Action;
use App\Models\ComplyingOffice;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\RequiredDocuments\RequiredDocumentResource;
use App\Filament\Resources\RequiredDocuments\Schemas\RequiredDocumentForm;

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
        // Original afterCreate logic
        RequiredDocumentForm::afterCreate($this->record, $this->data);

        $selectedOffices = $this->form->getState()['complying_offices'] ?? [];
        $status = '-1';

        // Create ComplyingOffice records
        foreach ($selectedOffices as $deptCode) {
            ComplyingOffice::create([
                'department_code' => $deptCode,
                'requirement_id'  => $this->record->id,
                'status'          => $status,
            ]);
        }

        // --------------------------
        // Filament Bell Notifications
        // --------------------------

        // Get all users in the selected offices
        $users = User::whereIn('department_code', $selectedOffices)->get();
        $requirementTitle = $this->record->requirement;
        $requiringAgency = $this->record->agency_name;
        $deadline = $this->record->due_date;

        foreach ($users as $user) {
            Notification::make()
                ->title('New Requirement Assigned')
                ->icon('heroicon-o-document-text')
                ->body("**{$requiringAgency}** assigned a new requirement: **{$requirementTitle}**. Deadline: **{$deadline->format('F j, Y')}**.")
                ->actions([
                    Action::make('View')
                        ->url(
                            RequiredDocumentResource::getUrl(
                                'edit',
                                ['record' => $this->record]
                            )
                        ),
                ])
                ->sendToDatabase($user);
        }
    }

    



}
