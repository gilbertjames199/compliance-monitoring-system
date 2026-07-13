<?php

namespace App\Filament\Resources\ComplyingOffices\Pages;

use App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource;
use App\Models\Office;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;

class CreateComplyingOffice extends CreateRecord
{
    protected static string $resource = ComplyingOfficeResource::class;
    
    protected function getFormSchema(): array
    {
        return [
            Select::make('department_codes')
                ->label('Complying Offices')
                ->multiple()
                ->required()
                ->options(
                    Office::orderBy('office')
                        ->pluck('office', 'department_code')
                        ->toArray()
                )
                ->preload()
                ->searchable()
                ->afterStateHydrated(function ($component, $record) {
                    // Safely load existing data for edit mode
                    $codes = $record->department_codes ?? [];
                    $component->state($codes);
                }),
        ];
    }

   protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure department_codes is always an array
        $data['department_codes'] = $data['department_codes'] ?? [];
        return $data;
    }


}
