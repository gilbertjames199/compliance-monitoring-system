<?php

namespace App\Filament\Resources\ComplyingOffices\Pages;

use App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;


class ListComplyingOffices extends ListRecords
{
    protected static string $resource = ComplyingOfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

     public function getTabs(): array
    {
        return [
            null => Tab::make('All'),
            'Not Complied' => Tab::make()->query(fn ($query) => $query->where('status', '-1')),
            'Partially Complied' => Tab::make()->query(fn ($query) => $query->where('status', '0')),
            'Complied' => Tab::make()->query(fn ($query) => $query->where('status', '1')),
          
        ];
    }
}
