<?php

namespace App\Filament\Widgets;

use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class StatsOverview extends BaseWidget
{
    // Widget title and description
    // protected ?string $heading = 'Compliance Overview';
    // protected ?string $description = 'A quick overview of all required documents and their compliance status.';

   
    protected function getStats(): array
    {
        return [
             // Total Required Documents
            Stat::make('Required Documents', RequiredDocument::count())
                ->description('All documents that need compliance')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart(RequiredDocument::latest()->take(7)->pluck('id')->toArray()),

            // Total Not Complied
            Stat::make('Not Complied Documents', ComplyingOffice::where('status', '-1')->count())
                ->description('Total number of not complied required document across all offices')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->chart(ComplyingOffice::where('status', '-1')->latest()->take(7)->pluck('id')->toArray()),

            // Total Partially Complied
            Stat::make('Partially Complied Documents', ComplyingOffice::where('status', '0')->count())
                ->description('Total required documents across offices that are partially complied')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('warning')
                ->chart(ComplyingOffice::where('status', '0')->latest()->take(7)->pluck('id')->toArray()),

            // Total Complied
            Stat::make('Complied Documents', ComplyingOffice::where('status', '1')->count())
                ->description('Total required documents across offices that have been fully complied')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart(ComplyingOffice::where('status', '1')->latest()->take(7)->pluck('id')->toArray()),
    
        ];
    }
}
