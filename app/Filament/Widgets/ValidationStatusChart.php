<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\ComplyingOffice;
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
            if ($user->hasRole('department_head')) {
                // Department head sees all documents in their department
                $query->where('department_code', $user->department_code);
            } elseif ($user->hasAnyRole(['AO', 'admin'])) {
                // AO or admin sees only non-confidential documents in their department
                $query->where('department_code', $user->department_code)
                      ->whereHas('requiredDocument', function ($q) {
                          $q->where('is_confidential', 0);
                      });
            }
        }

        $statuses = [
            'pending_review' => (clone $query)->where('validation_status', 'pending_review')->count(),
            'returned'       => (clone $query)->where('validation_status', 'returned')->count(),
            'validated'      => (clone $query)->where('validation_status', 'validated')->count(),
        ];

        return [
            'labels' => ['Pending Review', 'Returned', 'Validated'],
            'datasets' => [
                [
                    'label' => 'Validation Status',
                    'data' => array_values($statuses),
                    'backgroundColor' => [
                        '#FFC700', // warning
                        '#E91E63', // danger
                        '#1DB584', // success
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
