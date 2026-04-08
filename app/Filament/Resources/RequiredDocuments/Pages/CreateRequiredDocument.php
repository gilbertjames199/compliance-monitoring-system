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
        
        $modalUsers = User::whereIn('department_code', $selectedOffices)
            ->get()
            ->filter(function($user) {
                // Only show confidential if user has permission
                if ($this->record->is_confidential) {
                    return $user->can('ViewConfidential:RequiredDocument');
                }
                return true;
            });
        // For non-confidential, all users get modal notifications (including AO & admin)
        
        // $modalUsers = $modalUsers->get();
        $requirementTitle = $this->record->requirement;
        $requiringAgency = $this->record->agency_name;
        $deadline = $this->record->due_date;
        
        // Send modal notifications only to allowed users
        foreach ($modalUsers as $user) {
            $actions = [];

            if (!$this->record->is_confidential || $user->can('ViewConfidential:RequiredDocument')) {
                $complyingOffice = $complyingOfficeRecords[$user->department_code] ?? null;
                if ($complyingOffice) {
                    $actions[] = Action::make('View')
                        ->url(\App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource::getUrl('edit', ['record' => $complyingOffice]));
                }
            }

            $canView = !$this->record->is_confidential || $user->can('ViewConfidential:RequiredDocument');

            $body = $canView
                ? "{$requiringAgency} assigned a new requirement: {$requirementTitle}. Deadline: {$deadline->format('F j, Y')}."
                : "{$requiringAgency} assigned a new confidential requirement. Deadline: {$deadline->format('F j, Y')}.";

            $notificationData = array_merge(
                Notification::make()
                    ->title('New Requirement Assigned')
                    ->icon('heroicon-o-document-text')
                    ->body($body)
                    ->actions($actions)
                    ->getDatabaseMessage(),
                ['required_document_id' => $this->record->id]
            );

            DB::connection('mysql')
                ->table('notifications')
                ->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => Notification::class,
                    'notifiable_type' => User::class,
                    'notifiable_id'   => $user->getKey(),
                    'data'            => json_encode($notificationData),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
        }
    }



}
