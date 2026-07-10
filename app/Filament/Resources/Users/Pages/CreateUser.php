<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\UserDivision;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $accessibleDepartmentCodes = [];
    protected array $pendingDivisions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->accessibleDepartmentCodes = $data['accessible_department_codes'] ?? [];
        $this->pendingDivisions = $data['division_codes'] ?? []; // 👈 add
        
        unset($data['accessible_department_codes']);
        unset($data['division_codes']); // 👈 add

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncOfficeAssignments();
        $this->syncDivisions(); // 👈 add
    }

    protected function syncOfficeAssignments(): void
    {
        $departmentCodes = collect($this->accessibleDepartmentCodes)
            ->filter()
            ->reject(fn (string $departmentCode) => $departmentCode === $this->record->department_code)
            ->unique()
            ->values();

        $this->record->officeAssignments()->delete();

        foreach ($departmentCodes as $departmentCode) {
            $this->record->officeAssignments()->create([
                'department_code' => $departmentCode,
            ]);
        }
    }

    protected function syncDivisions(): void
    {
        UserDivision::where('user_id', $this->record->recid)->delete();

        foreach ($this->pendingDivisions as $divCode) {
            UserDivision::create([
                'user_id'         => $this->record->recid,
                'department_code' => $this->record->department_code,
                'division_code'   => $divCode,
            ]);
        }
    }
}
