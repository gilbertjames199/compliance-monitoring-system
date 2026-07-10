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
        $accessibleDepartmentCodes = $user->accessibleDepartmentCodes();

        // Divisions assigned to this user (same helper used in the other compliance widgets)
        $userDivisionCodes = collect(
            method_exists($user, 'divisionCodes') ? $user->divisionCodes() : []
        )->filter()->values();
        
        // 🔒 OFFICE SCOPE
        if (! $user->hasRoleSafe('super_admin')) {
            $query->whereIn('department_code', $accessibleDepartmentCodes);

            // 🔒 DIVISION SCOPING
            // Only count rows for non-division-tracked requirements, or rows
            // whose division_code matches one this user is assigned to.
            $query->where(function ($q) use ($userDivisionCodes) {
                $q->whereHas('requiredDocument', fn ($rq) =>
                    $rq->where('requires_division_tracking', false)
                        ->orWhereNull('requires_division_tracking')
                );

                if ($userDivisionCodes->isNotEmpty()) {
                    $q->orWhereIn('division_code', $userDivisionCodes->toArray());
                }
            });
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