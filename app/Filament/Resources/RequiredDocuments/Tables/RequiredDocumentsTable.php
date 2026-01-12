<?php

namespace App\Filament\Resources\RequiredDocuments\Tables;

use App\Models\User;
use App\Models\Office;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Mail;
use App\Mail\RequirementDeadlineMail;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\Blade;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Layout\View as LayoutView;

class RequiredDocumentsTable 
{
    protected static ?string $model = RequiredDocument::class;

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
             ->modifyQueryUsing(function (Builder $query) {

                $user = auth()->user();
                if (! $user) {
                    return;
                }

                // Superadmin sees everything
                if ($user->hasRole('super_admin')) {
                    return;
                }

                if ($user->department_code == 25 && $user->hasRole('super_admin')) {
                    return;
                }

                // Only show records where the requiring agency matches the user's office name
                $query->where('agency_name', $user->office->office);

                // Optionally hide confidential requirements from AO & Admin
                if ($user->hasAnyRole(['AO', 'admin'])) {
                    $query->where('is_confidential', false);
                }
            })
            
            ->columns([
                TextColumn::make('requirement')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('agency_name')
                    ->label('Requiring Agency')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('year')
                    ->searchable(),
                TextColumn::make('category.category')
                    ->label('Category')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('date_from')
                    ->label('Date From')
                    ->date()
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])// show actions column
            ->recordActions([

                Action::make('Notify Office')
                    ->action(function ($record, $data) {

                        // Get complying offices that are NOT yet complied
                        $complyingOffices = ComplyingOffice::where('requirement_id', $record->id)
                                                ->where('status', '!=', '1')
                                                ->get();

                        foreach ($complyingOffices as $office) {

                            // Users in this department
                            $users = User::where('department_code', $office->department_code)->get();

                            foreach ($users as $user) {

                                // Skip AO/Admin for confidential requirements
                                if ($record->is_confidential && $user->hasAnyRole(['AO','admin'])) {
                                    continue;
                                }

                                Mail::to($user->email)
                                    ->send(new RequirementDeadlineMail($record));
                            }
                        }

                    })
                    ->color('primary')
                    ->icon('heroicon-o-envelope'),

                Action::make('manage_compliance')
                    ->label('View/Update Complying Offices')
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
        // $query = parent::getTableQuery();

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
}
