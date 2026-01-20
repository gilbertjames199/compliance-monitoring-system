<?php

namespace App\Filament\Widgets;

use App\Models\ComplyingOffice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ComplianceStatusPieChart extends ChartWidget
{
    protected ?string $heading = 'Compliance Status Pie Chart';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $user = Auth::user();

        // Base query for ComplyingOffice
        $baseQuery = ComplyingOffice::query();

        // Apply filters based on role
        if (!$user->hasRole('super_admin')) {
            if ($user->hasRole('department_head')) {
                $baseQuery->where('department_code', $user->department_code);
            } elseif ($user->hasAnyRole(['AO', 'admin'])) {
                $baseQuery->where('department_code', $user->department_code)
                          ->whereHas('requiredDocument', function ($q) {
                              $q->where('is_confidential', 0);
                          });
            }
        }

        // Count by status
        $notComplied = (clone $baseQuery)->where('status', -1)->count();
        $partiallyComplied = (clone $baseQuery)->where('status', 0)->count();
        $complied = (clone $baseQuery)->where('status', 1)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Document Compliance',
                    'data' => [$notComplied, $partiallyComplied, $complied],
                    'backgroundColor' => [
                        '#ef4444',   // danger - red
                        '#f59e0b',   // warning - amber
                        '#22c55e',    // success - green
                    ],
                ],
            ],
            'labels' => ['Not Complied', 'Partially Complied', 'Complied'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
