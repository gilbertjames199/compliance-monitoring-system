<?php

namespace App\Filament\Resources\ComplyingOffices;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\ComplyingOffice;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\ComplyingOffices\Pages\EditComplyingOffice;
use App\Filament\Resources\ComplyingOffices\Pages\ViewComplyingOffice;
use App\Filament\Resources\ComplyingOffices\Pages\ListComplyingOffices;
use App\Filament\Resources\ComplyingOffices\Pages\CreateComplyingOffice;
use App\Filament\Resources\ComplyingOffices\Schemas\ComplyingOfficeForm;
use App\Filament\Resources\ComplyingOffices\Tables\ComplyingOfficesTable;

class ComplyingOfficeResource extends Resource
{
    protected static ?string $model = ComplyingOffice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $recordTitleAttribute = 'Complying Offices';

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
            'view' => ViewComplyingOffice::route('/{record}'),
            'edit' => EditComplyingOffice::route('/{record}/edit'),
        ];
    }
}
