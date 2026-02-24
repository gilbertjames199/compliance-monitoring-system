<?php

namespace App\Filament\Resources\RequiredDocuments\Pages;

use App\Filament\Resources\RequiredDocuments\RequiredDocumentResource;
use App\Filament\Resources\RequiredDocuments\Schemas\RequiredDocumentForm;
use App\Mail\DueDateReminderMail;
use App\Models\ComplyingOffice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;

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
        //         'required_document_id'  => $record->id,
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

        // Create ComplyingOffice records and store them for notification linking
        $complyingOfficeRecords = [];
        foreach ($selectedOffices as $deptCode) {
            $complyingOfficeRecords[$deptCode] = ComplyingOffice::create([
                'department_code' => $deptCode,
                'required_document_id'  => $this->record->id,
                'status'          => $status,
            ]);
        }

        // --------------------------
        // Filament Bell Notifications (Database)
        // --------------------------

        // Get all users in the selected offices
        $users = User::whereIn('department_code', $selectedOffices)->get();
        
        // Get modal notification users (only super_admin & department_head for confidential, everyone for non-confidential)
        $modalUsers = User::whereIn('department_code', $selectedOffices);
        
        if ($this->record->is_confidential) {
            // Only super_admin and department_head for confidential
            $allowedUserIds = DB::connection('mysql')
                ->table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('roles.name', ['super_admin', 'department_head'])
                ->where('model_type', User::class)
                ->pluck('model_id')
                ->toArray();
            $modalUsers->whereIn('recid', $allowedUserIds);
        }
        // For non-confidential, all users get modal notifications (including AO & admin)
        
        $modalUsers = $modalUsers->get();
        $requirementTitle = $this->record->requirement;
        $requiringAgency = $this->record->agency_name;
        $deadline = $this->record->due_date;
        
        // Send modal notifications only to allowed users
        foreach ($modalUsers as $user) {
            $actions = [];
            
            // Show View action if document is not confidential OR user is super_admin/department_head
            // Also show for admin and ao roles if document is not confidential
            $canViewRecord = !$this->record->is_confidential || 
                $user->hasRoleSafe('super_admin', 'department_head') ||
                (!$this->record->is_confidential && $user->hasRoleSafe('admin', 'AO'));
            
            if ($canViewRecord) {
                // Get the ComplyingOffice record for this user's department
                $complyingOffice = $complyingOfficeRecords[$user->department_code] ?? null;
                
                if ($complyingOffice) {
                    $actions[] = Action::make('View')
                        ->url(
                            \App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource::getUrl(
                                'edit',
                                ['record' => $complyingOffice]
                            )
                        );
                }
            }
            
            Notification::make()
                ->title('New Requirement Assigned')
                ->icon('heroicon-o-document-text')
                ->body("**{$requiringAgency}** assigned a new requirement: **{$requirementTitle}**. Deadline: **{$deadline->format('F j, Y')}**.")
                ->actions($actions)
                ->sendToDatabase($user);
        }
    }



}
