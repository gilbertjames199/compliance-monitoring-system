<?php

namespace App\Filament\Resources\Offices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OfficeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('department_code'),
                // TextEntry::make('ffunccod')
                //     ->placeholder('-'),
                TextEntry::make('office'),
                TextEntry::make('short_name')
            
                //     ->placeholder('-'),
                // TextEntry::make('empl_id')
                //     ->placeholder('-'),
                // TextEntry::make('designation')
                //     ->placeholder('-'),
                // TextEntry::make('created_at')
                //     ->dateTime()
                //     ->placeholder('-'),
                // TextEntry::make('updated_at')
                //     ->dateTime()
                //     ->placeholder('-'),
            ]) ->columns(3);
    }
}
