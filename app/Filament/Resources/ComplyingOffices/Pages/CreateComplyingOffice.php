<?php

namespace App\Filament\Resources\ComplyingOffices\Pages;

use App\Models\Office;
use App\Models\ComplyingOffice;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource;

class CreateComplyingOffice extends CreateRecord
{
    protected static string $resource = ComplyingOfficeResource::class;

     protected function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Select::make('department_codes')
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
