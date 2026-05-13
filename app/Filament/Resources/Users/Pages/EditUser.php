<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $accessibleDepartmentCodes = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->accessibleDepartmentCodes = $data['accessible_department_codes'] ?? [];
        unset($data['accessible_department_codes']);

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
    }
}
