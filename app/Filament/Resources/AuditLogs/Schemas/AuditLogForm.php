<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use App\Models\ComplyingOffice;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event')
                    ->label('Event')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'reminder_sent'    => '📧 Reminder Sent',
                        'reminder_skipped' => '⚠️ Reminder Skipped',
                        'reminder_failed'  => '❌ Reminder Failed',
                        default            => ucfirst(str_replace('_', ' ', $state ?? '-')),
                    })
                    ->disabled(),

                TextInput::make('requirement_name')
                    ->label('Requirement')
                    ->default(fn ($record) => $record->requirement_name ?? '-')
                    ->disabled(),

                TextInput::make('user_id')
                    ->label('Action By')
                    ->formatStateUsing(function ($state) {
                        $user = User::find($state);
                        return $user?->FullName ?? $user?->UserName ?? "User #{$state}";
                    })
                    ->disabled(),

                
                // DateTimePicker::make('action_at')
                //     ->label('Action At')
                //     ->displayFormat('M d, Y h:i A')
                //     ->timezone('Asia/Manila')
                //     ->disabled(),

                TextInput::make('action_at')
                    ->label('Action At')
                    ->formatStateUsing(fn ($state) =>
                        $state
                            ? Carbon::parse($state)
                                ->timezone('Asia/Manila')
                                ->format('M d, Y h:i A')
                            : '-'
                    )
                    ->disabled(),

                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('office_name')
                            ->label('Complying Office')
                            ->disabled(),

                        TextInput::make('division_name')
                            ->label('Division')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($state, $record) {
                                if (!$record?->complying_office_id) {
                                    return '—';
                                }

                                $complyingOffice = ComplyingOffice::find($record->complying_office_id);

                                if (!$complyingOffice || blank($complyingOffice->division_code)) {
                                    return '—';
                                }

                                $division = DB::connection('mysql2')
                                    ->table('fms.divisions')
                                    ->where('department_code', $complyingOffice->department_code)
                                    ->where('division_code', $complyingOffice->division_code)
                                    ->first();

                                return $division
                                    ? $division->division_name1 . (!empty($division->division_short_name) ? ' (' . $division->division_short_name . ')' : '')
                                    : $complyingOffice->division_code;
                            })
                            ->visible(fn ($record) => filled($record?->complying_office_id)),

                        TextInput::make('requiring_agency_name')
                            ->label('Requiring Agency')
                            ->default(fn ($record) => $record->requiring_agency_name ?? '-')
                            ->disabled(),
                    ]),

                TextInput::make('old_status')
                    ->label('Old Compliance Status')
                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                        '-1'    => 'Not Complied',
                        '0'     => 'Partially Complied',
                        '1'     => 'Complied',
                        default => '-',
                    })
                    ->disabled(),

                TextInput::make('new_status')
                    ->label('New Compliance Status')
                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                        '-1'    => 'Not Complied',
                        '0'     => 'Partially Complied',
                        '1'     => 'Complied',
                        default => '-',
                    })
                    ->disabled(),

                TextInput::make('old_validation_status')
                    ->label('Old Validation Status')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending_review' => 'Pending Review',
                        'returned'       => 'Returned',
                        'validated'      => 'Validated',
                        default          => '-',
                    })
                    ->disabled(),

                TextInput::make('new_validation_status')
                    ->label('New Validation Status')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending_review' => 'Pending Review',
                        'returned'       => 'Returned',
                        'validated'      => 'Validated',
                        default          => '-',
                    })
                    ->disabled(),

                Textarea::make('remarks')
                    ->label('Remarks')
                    ->default(fn ($record) => $record->remarks ?? '-')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }
}