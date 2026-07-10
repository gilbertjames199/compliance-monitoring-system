<?php

namespace App\Filament\Pages;

use App\Models\Office;
use App\Models\Pis\Division;
use App\Models\UserDivision;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

class SelectDivision extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.select-division';

    public ?string $department_code = null;
    public ?array $division_codes = [];

    public function mount(): void
    {
        $user = auth()->user();

        if (! empty($user->divisionCodes())) {
            redirect()->intended('/admin');
            return;
        }

        $this->department_code = $user->department_code;
    }

    protected function getFormSchema(): array
    {
        $user = auth()->user();
        $accessibleCodes = $user->accessibleDepartmentCodes();

        $departments = Office::whereIn('department_code', $accessibleCodes)
            ->orderBy('office')
            ->get()
            ->mapWithKeys(fn ($office) => [
                $office->department_code => $office->office .
                    (! empty($office->short_name) ? ' (' . $office->short_name . ')' : ''),
            ]);

        return [
            Grid::make(1)
                ->schema([
                    Select::make('department_code')
                        ->label('Department / Office')
                        ->helperText('This is the department/office you are currently assigned to. If this is incorrect, please contact PICTO to have it changed — do not proceed if this office is wrong.')
                        ->options($departments)
                        ->disabled($departments->count() <= 1)
                        ->dehydrated()
                        ->native(false)
                        ->live()
                        ->required()
                        ->prefixIcon('heroicon-o-building-office')
                        ->afterStateUpdated(fn (callable $set) => $set('division_codes', []))
                        ->columnSpan(1),

                    Select::make('division_codes')
                        ->label('Division(s)')
                        ->helperText('Select all divisions you actively work under. You can pick more than one.')
                        ->multiple()
                        ->options(function (Get $get) {
                            $deptCode = $get('department_code');

                            if (! $deptCode) {
                                return [];
                            }

                            return Division::where('department_code', $deptCode)
                                ->orderBy('division_name1')
                                ->get()
                                ->mapWithKeys(fn ($d) => [
                                    $d->division_code => $d->division_name1 .
                                        (! empty($d->division_short_name)
                                            ? ' (' . $d->division_short_name . ')'
                                            : ''),
                                ]);
                        })
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->prefixIcon('heroicon-o-user-group')
                        ->columnSpan(1),
                ]),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        $departmentCode = $data['department_code'] ?? $this->department_code;

        foreach ($data['division_codes'] as $divisionCode) {
            UserDivision::firstOrCreate([
                'user_id' => $user->recid,
                'department_code' => $departmentCode,
                'division_code' => $divisionCode,
            ]);
        }

        Notification::make()
            ->title('Division saved')
            ->success()
            ->send();

        redirect()->intended('/admin');
    }
}