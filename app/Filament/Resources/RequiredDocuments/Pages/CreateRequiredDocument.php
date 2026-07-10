<?php

namespace App\Filament\Resources\RequiredDocuments\Pages;

use App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource;
use App\Filament\Resources\RequiredDocuments\RequiredDocumentResource;
use App\Filament\Resources\RequiredDocuments\Schemas\RequiredDocumentForm;
use App\Mail\DueDateReminderMail;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocumentDivision;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateRequiredDocument extends CreateRecord
{
    protected static string $resource = RequiredDocumentResource::class;

    protected array $pendingOffices = [];      // 👈 add
    protected array $pendingDivisions = [];    // 👈 add

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = RequiredDocumentForm::mutateFormDataBeforeCreate($data);

        // 👇 stash into page properties before data is lost
        $this->pendingOffices   = $data['_selected_offices'] ?? [];
        $this->pendingDivisions = $data['_selected_divisions'] ?? [];

        return $data;
    }


    
    protected function afterCreate(): void
    {
        $record = $this->record;
        $status = -1;

        if ($record->requires_division_tracking && !empty($this->pendingDivisions)) {
            // One complying_office row PER DIVISION (each division submits separately)
            foreach ($this->pendingDivisions as $entry) {
                [$deptCode, $divCode] = explode('|', $entry);
                \App\Models\ComplyingOffice::firstOrCreate(
                    [
                        'required_document_id' => $record->id,
                        'department_code'      => $deptCode,
                        'division_code'        => $divCode,
                    ],
                    ['status' => $status]
                );
            }

            // Also save to required_document_divisions pivot
            foreach ($this->pendingDivisions as $entry) {
                [$deptCode, $divCode] = explode('|', $entry);
                \App\Models\RequiredDocumentDivision::firstOrCreate([
                    'required_document_id' => $record->id,
                    'department_code'      => $deptCode,
                    'division_code'        => $divCode,
                ]);
            }
        } else {
            // No division tracking → one row per office
            foreach ($this->pendingOffices as $deptCode) {
                \App\Models\ComplyingOffice::create([
                    'department_code'      => $deptCode,
                    'required_document_id' => $record->id,
                    'status'               => $status,
                ]);
            }
        }

        // Recurring job
        if ($record->is_recurring && $record->recurrence_type) {
            \App\Jobs\CreateRecurringDocuments::dispatch(
                $record->fresh(),
                $record->recurrence_type,
                $record->recurrence_interval
            )->afterCommit();
        }

        // Notifications — deduplicate by department_code so office gets 1 notification
        $complyingOfficeDeptCodes = $record->complyingOffices()
            ->pluck('department_code')
            ->unique()
            ->toArray();

        $modalUsers = \App\Models\User::whereIn('department_code', $complyingOfficeDeptCodes)
            ->get()
            ->filter(function ($user) use ($record) {
                // Division tracking: only notify users whose division is assigned
                if ($record->requires_division_tracking) {
                    $userDivisionCodes = $user->divisionCodes();
                    return $record->complyingOffices()
                        ->where('department_code', $user->department_code)
                        ->whereIn('division_code', $userDivisionCodes)
                        ->exists();
                }

                if ($record->is_confidential) {
                    return $user->can('ViewConfidential:RequiredDocument');
                }

                return true;
            });

        $requirementTitle = $record->requirement;
        $requiringAgency  = $record->agency_name;
        $deadline         = $record->due_date;

        foreach ($modalUsers as $user) {
            // Find the specific complying_office row for this user's division
            $complyingOffice = $record->complyingOffices()
                ->where('department_code', $user->department_code)
                ->when($record->requires_division_tracking, function ($q) use ($user) {
                    $userDivisionCodes = $user->divisionCodes();
                    $q->whereIn('division_code', $userDivisionCodes);
                })
                ->first();

            $actions = [];
            if ($complyingOffice && (!$record->is_confidential || $user->can('ViewConfidential:RequiredDocument'))) {
                $actions[] = \Filament\Actions\Action::make('View')
                    ->url(\App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource::getUrl('edit', ['record' => $complyingOffice]));
            }

            $canView = !$record->is_confidential || $user->can('ViewConfidential:RequiredDocument');
            $body = $canView
                ? "{$requiringAgency} assigned a new requirement: {$requirementTitle}. Deadline: {$deadline->format('F j, Y')}."
                : "{$requiringAgency} assigned a new confidential requirement. Deadline: {$deadline->format('F j, Y')}.";

            $notificationData = array_merge(
                \Filament\Notifications\Notification::make()
                    ->title('New Requirement Assigned')
                    ->icon('heroicon-o-document-text')
                    ->body($body)
                    ->actions($actions)
                    ->getDatabaseMessage(),
                ['required_document_id' => $record->id]
            );

            \Illuminate\Support\Facades\DB::connection('mysql')->table('notifications')->insert([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => \Filament\Notifications\Notification::class,
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id'   => $user->getKey(),
                'data'            => json_encode($notificationData),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }



}
