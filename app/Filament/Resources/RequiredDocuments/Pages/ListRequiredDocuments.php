<?php

namespace App\Filament\Resources\RequiredDocuments\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Resources\RequiredDocuments\RequiredDocumentResource;

class ListRequiredDocuments extends ListRecords
{
    protected static string $resource = RequiredDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
