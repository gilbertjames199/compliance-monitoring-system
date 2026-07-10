<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\UserDivision;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $accessibleDepartmentCodes = [];
    protected array $pendingDivisions = [];

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->accessibleDepartmentCodes = $data['accessible_department_codes'] ?? [];
        $this->pendingDivisions = $data['division_codes'] ?? []; // 👈 add

        unset($data['accessible_department_codes']);
        unset($data['division_codes']); // 👈 add

        return $data;
    }

    protected function afterSave(): void
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
