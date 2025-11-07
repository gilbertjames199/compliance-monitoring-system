<?php

namespace App\Filament\Resources\DocumentCategories;

use App\Filament\Resources\DocumentCategories\Pages\CreateDocumentCategory;
use App\Filament\Resources\DocumentCategories\Pages\EditDocumentCategory;
use App\Filament\Resources\DocumentCategories\Pages\ListDocumentCategories;
use App\Filament\Resources\DocumentCategories\Schemas\DocumentCategoryForm;
use App\Filament\Resources\DocumentCategories\Tables\DocumentCategoriesTable;
use App\Models\ComplyingOffice;
use App\Models\DocumentCategory;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DocumentCategoryResource extends Resource
{
    protected static ?string $model = DocumentCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'DOcument Categories';

    public static function form(Schema $schema): Schema
    {
        return DocumentCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentCategoriesTable::configure($table);
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
            'index' => ListDocumentCategories::route('/'),
            'create' => CreateDocumentCategory::route('/create'),
            'edit' => EditDocumentCategory::route('/{record}/edit'),
        ];
    }
    /*
    protected static function afterCreate($record, array $data): void
    {
        if (!empty($data['complying_offices'])) {
            foreach ($data['complying_offices'] as $department_code) {
                ComplyingOffice::create([
                    'department_code' => $department_code,
                    'requirement_id' => $record->id,
                    'status' => -1, // Default: Not Complied
                ]);
            }
        }
    }

    protected static function afterSave($record, array $data): void
    {
        // Optional if you also want to sync when editing
        $selectedOffices = collect($data['complying_offices'] ?? []);
        $existing = $record->complyingOffices()->pluck('department_code');

        // Add new ones
        $toAdd = $selectedOffices->diff($existing);
        foreach ($toAdd as $department_code) {
            ComplyingOffice::create([
                'department_code' => $department_code,
                'requirement_id' => $record->id,
                'status' => -1,
            ]);
        }

        // Remove unselected ones
        $toRemove = $existing->diff($selectedOffices);
        if ($toRemove->isNotEmpty()) {
            $record->complyingOffices()
                ->whereIn('department_code', $toRemove)
                ->delete();
        }
    }
    */

    public function saveWithComplyingOffices(array $attributes)
    {
        // Save the RequiredDocument first
        $this->fill($attributes);
        $this->save();

        if (isset($attributes['complying_offices'])) {
            $selectedOffices = collect($attributes['complying_offices']);

            // Get existing complying offices for this requirement
            $existing = $this->complyingOffices()->pluck('department_code');

            // Add new ones
            $toAdd = $selectedOffices->diff($existing);
            foreach ($toAdd as $department_code) {
                ComplyingOffice::create([
                    'department_code' => $department_code,
                    'requirement_id'  => $this->id,
                    'status'          => -1, // Default: Not Complied
                ]);
            }

            // Remove unselected ones
            $toRemove = $existing->diff($selectedOffices);
            if ($toRemove->isNotEmpty()) {
                $this->complyingOffices()
                    ->whereIn('department_code', $toRemove)
                    ->delete();
            }
        }

        return $this;
    }

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        // This will call our custom method instead of default save
        // $record = new \App\Models\RequiredDocument();
        // $record->saveWithComplyingOffices($data);
        dd($data);
        // Prevent Filament from running its own save since we already did
        return [];
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        dd($data);
        $record = \App\Models\RequiredDocument::find($data['id']);
        if ($record) {
            $record->saveWithComplyingOffices($data);
        }

        // Prevent double save
        return [];
    }

    // public function save()
    // {
    //     $data = $this->form->getState();
    //     dd($data); // 👈 Debug before saving manually
    // }


    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Document Category saved')
            ->body('The Document Category has been saved');
    }


}
