<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\ComplyingOffice;
use Illuminate\Support\Facades\Auth;

class ValidationStatusChart extends ChartWidget
{
    protected ?string $heading = 'Validation Status Chart ';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $user = Auth::user();

         // ❗ Show nothing if user has no role
        if (! $user || $user->roles->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Validation Status',
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
            ];
        }

        $query = ComplyingOffice::query();
        
        // 🔒 OFFICE SCOPE
        if (! $user->hasRoleSafe('super_admin')) {
            $query->where('department_code', $user->department_code);
        }

        // 🔒 CONFIDENTIAL CONTROL
        if (! $user->can('ViewConfidential:RequiredDocument')) {
            $query->whereHas('requiredDocument', fn ($q) =>
                $q->where('is_confidential', false)
            );
        }

        $statuses = [
            'pending_review' => (clone $query)->where('validation_status', 'pending_review')->count(),
            'returned'       => (clone $query)->where('validation_status', 'returned')->count(),
            'validated'      => (clone $query)->where('validation_status', 'validated')->count(),
        ];

        return [
            'labels'   => ['Pending Review', 'Returned', 'Validated'],
            'datasets' => [
                [
                    'label'           => 'Validation Status',
                    'data'            => array_values($statuses),
                    'backgroundColor' => [
                        '#ff9500', // warning - Pending Review
                        '#E91E63', // danger  - Returned
                        '#1DB584', // success - Validated
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
