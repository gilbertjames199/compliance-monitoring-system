<?php

namespace App\Filament\Resources\RequiredDocuments;

use App\Filament\Resources\RequiredDocuments\Pages\CreateRequiredDocument;
use App\Filament\Resources\RequiredDocuments\Pages\EditRequiredDocument;
use App\Filament\Resources\RequiredDocuments\Pages\ListRequiredDocuments;
use App\Filament\Resources\RequiredDocuments\RelationManagers\ComplyingOfficesRelationManager;
use App\Filament\Resources\RequiredDocuments\Schemas\RequiredDocumentForm;
use App\Filament\Resources\RequiredDocuments\Tables\RequiredDocumentsTable;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RequiredDocumentResource extends Resource
{
    protected static ?string $model = RequiredDocument::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document';


    protected static ?string $recordTitleAttribute = 'Required Documents';

    public static function form(Schema $schema): Schema
    {
        return RequiredDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequiredDocumentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ComplyingOfficesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequiredDocuments::route('/'),
            'create' => CreateRequiredDocument::route('/create'),
            'edit' => EditRequiredDocument::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // temporarily store selected offices
        // dd($data['_selected_offices']);
        $data['_selected_offices'] = $data['selected_offices'] ?? [];
        $data['_status'] = $data['status'] ?? -1;

        unset($data['selected_offices'], $data['status']);

        return $data;
    }

    public static function afterCreate(RequiredDocument $record, array $data): void
    {
        // dd($record, $data);
        $selectedOffices = $data['_selected_offices'] ?? [];
        $status = $data['_status'] ?? -1;

        foreach ($selectedOffices as $departmentCode) {
            ComplyingOffice::create([
                'department_code' => $departmentCode,
                'requirement_id'  => $record->id,
                'status'          => $status,
            ]);
        }

        Notification::make()
            ->title('Required Document and Complying Offices saved successfully!')
            ->success()
            ->send();
    }

    public static function getModel(): string
    {
        return \App\Models\RequiredDocument::class;
    }


    // public static function table(Table $table): Table
    // {
    //     return $table
    //         ->columns([
    //             Tables\Columns\TextColumn::make('requirement')->sortable()->searchable(),
    //             Tables\Columns\TextColumn::make('year'),
    //         ])
    //         ->actions([
    //             Tables\Actions\EditAction::make(),
    //         ]);
    // }
}
