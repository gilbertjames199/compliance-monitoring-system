<?php

namespace App\Filament\Resources\DocumentCategories\Pages;

use App\Filament\Resources\DocumentCategories\DocumentCategoryResource;
use App\Filament\Resources\DocumentCategories\Schemas\DocumentCategoryForm;
use App\Models\ComplyingOffice;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateDocumentCategory extends CreateRecord
{
    protected static string $resource = DocumentCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

}