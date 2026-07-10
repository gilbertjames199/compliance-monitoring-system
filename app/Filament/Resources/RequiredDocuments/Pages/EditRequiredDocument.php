<?php

namespace App\Filament\Resources\RequiredDocuments\Pages;

use App\Filament\Resources\RequiredDocuments\RequiredDocumentResource;
use App\Models\RequiredDocumentDivision;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRequiredDocument extends EditRecord
{
    protected static string $resource = RequiredDocumentResource::class;

    protected array $pendingDivisions = [];

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
        $this->pendingDivisions = $data['required_divisions'] ?? [];

        // Deduplicate offices in case division rows inflated it
        $data['complying_offices'] = collect($data['complying_offices'] ?? [])
            ->unique()
            ->values()
            ->toArray();

        unset($data['required_divisions']);

        if (empty($data['is_recurring']) || ($data['recurrence_type'] ?? null) !== 'custom') {
            $data['recurrence_interval'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $state  = $this->form->getState();
        $selected = $state['complying_offices'] ?? [];
        $record = $this->record;

        if ($record->requires_division_tracking && !empty($this->pendingDivisions)) {

            $expectedKeys = collect($this->pendingDivisions);

            $existingRows = $record->complyingOffices()
                ->whereNotNull('division_code')
                ->get();

            $existingKeys = $existingRows
                ->map(fn ($co) => $co->department_code . '|' . $co->division_code)
                ->toBase();

            $lockedKeys = $existingRows
                ->whereIn('status', [0, 1])
                ->map(fn ($co) => $co->department_code . '|' . $co->division_code)
                ->toBase();

            $toRemove = $existingKeys->diff($expectedKeys);

            // 🔒 Block removal of locked (complied/partially complied) divisions
            $blockedRemovals = $toRemove->intersect($lockedKeys);
            $toRemove = $toRemove->diff($lockedKeys);

            if ($blockedRemovals->isNotEmpty()) {

            $names = collect();

            foreach ($blockedRemovals as $entry) {
                [$deptCode, $divCode] = explode('|', $entry);

                $division = \Illuminate\Support\Facades\DB::connection('mysql2')
                    ->table('fms.divisions')
                    ->where('department_code', $deptCode)
                    ->where('division_code', $divCode)
                    ->first();

                if ($division) {
                    $names->push(
                        $division->division_name1 .
                        (!empty($division->division_short_name)
                            ? " ({$division->division_short_name})"
                            : '')
                    );
                } else {
                    $names->push($entry);
                }
            }

            Notification::make()
                ->title('Cannot remove some divisions')
                ->body('Already complied or partially complied: ' . $names->implode(', '))
                ->danger()
                ->send();

            $expectedKeys = $expectedKeys
                ->merge($blockedRemovals)
                ->unique()
                ->values();
        }

            $toAdd = $expectedKeys->diff($existingKeys);

            foreach ($toRemove as $entry) {
                [$deptCode, $divCode] = explode('|', $entry);
                $record->complyingOffices()
                    ->where('department_code', $deptCode)
                    ->where('division_code', $divCode)
                    ->delete();
            }

            foreach ($toAdd as $entry) {
                [$deptCode, $divCode] = explode('|', $entry);
                $record->complyingOffices()->create([
                    'department_code'   => $deptCode,
                    'division_code'     => $divCode,
                    'status'            => '-1',
                    'validation_status' => 'pending_review',
                ]);
            }

            // Also remove office-level rows (null division_code) for these dept codes,
            // but only ones that aren't themselves locked
            $deptCodes = $expectedKeys->map(fn ($e) => explode('|', $e)[0])->unique();

            $record->complyingOffices()
                ->whereIn('department_code', $deptCodes)
                ->whereNull('division_code')
                ->whereNotIn('status', [0, 1])
                ->delete();

        } else {
            // Office-level sync (no division tracking)
            $existingOfficeRows = $record->complyingOffices()
                ->whereNull('division_code')
                ->get();

            $existing = $existingOfficeRows->pluck('department_code')->toBase();
            $lockedOffices = $existingOfficeRows
                ->whereIn('status', [0, 1])
                ->pluck('department_code')
                ->toBase();

            $toRemove = collect($existing)->diff($selected);
            $blockedOfficeRemovals = $toRemove->intersect($lockedOffices);
            $toRemove = $toRemove->diff($lockedOffices);

            if ($blockedOfficeRemovals->isNotEmpty()) {
                Notification::make()
                    ->title('Cannot remove some offices')
                    ->body('Already complied or partially complied: ' . $blockedOfficeRemovals->implode(', '))
                    ->danger()
                    ->send();

                $selected = array_values(array_unique(array_merge($selected, $blockedOfficeRemovals->toArray())));
            }

            $toAdd = array_diff($selected, $existing->toArray());

            if ($toRemove->isNotEmpty()) {
                $record->complyingOffices()
                    ->whereIn('department_code', $toRemove)
                    ->whereNull('division_code')
                    ->delete();
            }

            foreach ($toAdd as $deptCode) {
                $record->complyingOffices()->create([
                    'department_code'   => $deptCode,
                    'status'            => '-1',
                    'validation_status' => 'pending_review',
                ]);
            }

            // 🔒 Only remove leftover division-tracked rows that are NOT locked
            $record->complyingOffices()
                ->whereNotNull('division_code')
                ->whereNotIn('status', [0, 1])
                ->delete();
        }

        // Sync required_document_divisions pivot — but never drop divisions
        // that are still locked at the ComplyingOffice level
        $lockedPivotKeys = $record->complyingOffices()
            ->whereNotNull('division_code')
            ->whereIn('status', [0, 1])
            ->get()
            ->map(fn ($co) => $co->department_code . '|' . $co->division_code)
            ->toBase();

        $finalDivisions = collect($this->pendingDivisions)
            ->merge($lockedPivotKeys)
            ->unique()
            ->values();

        $record->requiredDocumentDivisions()->delete();
        foreach ($finalDivisions as $entry) {
            [$deptCode, $divCode] = explode('|', $entry);
            RequiredDocumentDivision::create([
                'required_document_id' => $record->id,
                'department_code'      => $deptCode,
                'division_code'        => $divCode,
            ]);
        }
    }



}
