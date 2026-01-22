<?php

namespace App\Filament\Resources\Offices\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;

class OfficeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Office Information')
                    ->schema([
                        TextEntry::make('department_code')
                            ->label('Department Code'),

                        TextEntry::make('office')
                            ->label('Office'),

                        TextEntry::make('short_name')
                            ->label('Short Name'),

                        TextEntry::make('designation')
                            ->label('Designation'),
                    ])
                    ->columnSpanFull()
                    ->columns(2)

                // TextEntry::make('department_code'),
                // // TextEntry::make('ffunccod')
                // //     ->placeholder('-'),
                // TextEntry::make('office'),
                // TextEntry::make('short_name')
            
                // //     ->placeholder('-'),
                // // TextEntry::make('empl_id')
                // //     ->placeholder('-'),
                // // TextEntry::make('designation')
                // //     ->placeholder('-'),
                // // TextEntry::make('created_at')
                // //     ->dateTime()
                // //     ->placeholder('-'),
                // // TextEntry::make('updated_at')
                // //     ->dateTime()
                // //     ->placeholder('-'),
            ]) ->columns(3);
    }
}
