<?php

namespace App\Filament\Resources\RequiredDocuments\Tables;

use App\Models\Office;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\Layout\View as LayoutView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;

class RequiredDocumentsTable extends Resource
{
    public static function configure(Table $table): Table
    {
        // dd(auth()->user());


            // ->query(function ($query) {
            //     $user = auth()->user();
            //     $departmentCode = $user->department_code;
            //     // dd($user);
            //     // If department_code = 25, show all records
            //     if ($departmentCode == 25) {
            //         return $query;
            //     }

            //     // Otherwise, filter records based on related complyingOffices
            //     return $query->whereHas('complyingOffices', function ($q) use ($departmentCode) {
            //         $q->where('department_code', $departmentCode);
            //     });
            // })
        return $table
            ->columns([
                TextColumn::make('requirement')
                    ->searchable(),
                TextColumn::make('agency_name')
                    ->searchable(),
                TextColumn::make('year')
                    ->searchable(),
                TextColumn::make('category.category')
                    ->label('Category')
                    ->searchable(),

            ])
            ->filters([
                //
            ])// 👈 show actions column
            ->recordActions([
                Action::make('manage_compliance')
                    ->label('View / Update Complying Offices')
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->modalHeading(fn($record) => "Complying Offices for '{$record->requirement}'")
                    ->modalSubmitActionLabel('Save Changes')
                    ->modalWidth('4xl')
                    ->schema(function ($record) {
                        // 🔹 Retrieve complying offices with office name via relationship
                        $offices = $record->complyingOffices()->get()->map(function ($complying) {
                                $office = Office::on('mysql2')
                                    ->where('department_code', $complying->department_code)
                                    ->first();

                                $complying->office_name = $office?->office ?? 'N/A';
                                return $complying;
                            });

                        if ($offices->isEmpty()) {
                            return [
                                View::make('filament.custom.no-compliance'),
                            ];
                        }

                        // 🔹 Build a dynamic form field per complying office
                        // $fields = [];
                        $fields[] = Blade::component('filament.custom.office-header');

                        foreach ($offices as $office) {
                            $fields[] = Grid::make(12)->schema([
                                TextInput::make("office_{$office->id}_name")
                                    ->label(false)
                                    ->default($office->office_name)
                                    ->columnSpan(8)
                                    ->disabled(),
                                // TextInput::make("office_{$office->id}_code")
                                //     ->label('Code')
                                //     ->default($office->department_code)
                                //     ->disabled(),

                                Select::make("office_{$office->id}_status")
                                    ->label(false)
                                    ->options([
                                        -1 => 'Not Complied',
                                        0 => 'Partially Complied',
                                        1 => 'Complied',
                                    ])
                                    ->default($office->status)
                                    ->columnSpan(4)
                                    ->native(false),
                            ]);
                        }

                        return $fields;
                    })
                    ->action(function ($record, array $data) {
                        // 🔹 Update each complying office’s status
                        $offices = $record->complyingOffices;
                        foreach ($offices as $office) {
                            $fieldKey = "office_{$office->id}_status";
                            if (isset($data[$fieldKey])) {
                                $office->update(['status' => (int) $data[$fieldKey]]);
                            }
                        }
                        Notification::make()
                            ->title('Compliance statuses updated successfully!')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
    public static function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $user = auth()->user();
        $departmentCode = $user->department_code ?? null;
        // dd($user);
        if ($departmentCode == 25) {
            return $query; // show all
        }

        return $query->whereHas('complyingOffices', function ($q) use ($departmentCode) {
            $q->where('department_code', $departmentCode);
        });
    }
    // TextColumn::make('created_at')
    //     ->dateTime()
    //     ->sortable()
    //     ->toggleable(isToggledHiddenByDefault: true),
    // TextColumn::make('updated_at')
    //     ->dateTime()
    //     ->sortable()
    //     ->toggleable(isToggledHiddenByDefault: true),
    // TextColumn::make('is_external')
    //     ->searchable(),
    // TextColumn::make('requiring_agency_internal')
    //     ->searchable(),
    // TextColumn::make('is_confidential')
    //     ->searchable(),
    // TextColumn::make('date_from')
    //     ->searchable(),
    // TextColumn::make('due_date')
    //     ->searchable(),
    // TextColumn::make('is_recurring')
    //     ->searchable(),
}
