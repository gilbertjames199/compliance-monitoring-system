<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class OverduePerOfficeChart extends ChartWidget
{
    protected ?string $heading = 'Overdue Per Office Chart';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
