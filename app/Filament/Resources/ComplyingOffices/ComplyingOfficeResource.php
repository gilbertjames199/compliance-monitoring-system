<?php

namespace App\Filament\Resources\ComplyingOffices;

use App\Filament\Resources\ComplyingOffices\Pages\CreateComplyingOffice;
use App\Filament\Resources\ComplyingOffices\Pages\EditComplyingOffice;
use App\Filament\Resources\ComplyingOffices\Pages\ListComplyingOffices;
use App\Filament\Resources\ComplyingOffices\Schemas\ComplyingOfficeForm;
use App\Filament\Resources\ComplyingOffices\Tables\ComplyingOfficesTable;
use App\Models\ComplyingOffice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ComplyingOfficeResource extends Resource
{
    protected static ?string $model = ComplyingOffice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Complying Officesw';

    public static function form(Schema $schema): Schema
    {
        return ComplyingOfficeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComplyingOfficesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplyingOffices::route('/'),
            'create' => CreateComplyingOffice::route('/create'),
            'edit' => EditComplyingOffice::route('/{record}/edit'),
        ];
    }
}
