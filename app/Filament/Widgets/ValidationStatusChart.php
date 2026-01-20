<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use App\Models\ComplyingOffice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ValidationStatusChart extends ChartWidget
{
    protected ?string $heading = 'Validation Status Chart';
    protected static ?int $sort = 3;

     protected function getData(): array
    {
        $user = Auth::user();

        $query = ComplyingOffice::query()->with('requiredDocument');

        if (!$user->hasRole('super_admin')) {
            $query->where('department_code', $user->department_code);
        }

        $statuses = [
            'pending_review' => $query->clone()->where('validation_status', 'pending_review')->count(),
            'returned'       => $query->clone()->where('validation_status', 'returned')->count(),
            'validated'      => $query->clone()->where('validation_status', 'validated')->count(),
        ];

        return [
            'labels' => [
                'Pending Review',
                'Returned',
                'Validated',
            ],
            'datasets' => [
                [
                    'label' => 'Validation Status',
                    'data' => array_values($statuses),
                    'backgroundColor' => [
                        '#f59e0b', // warning
                        '#ef4444', // danger
                        '#22c55e', // success
                    ],
                ],
            ],
        ];
    }


    protected function getType(): string
    {
        return 'pie';
    }
}
