<?php

namespace App\Filament\Widgets;

use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ComplianceStatusPieChart extends ChartWidget
{
    protected ?string $heading = 'Compliance Status Pie Chart';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $user = Auth::user();

        // ❗ Show nothing if user has no role
        if (! $user || $user->roles->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Document Compliance',
                        'data'  => [],
                        'backgroundColor' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $complyingQuery      = ComplyingOffice::query();
        $requiredDocsQuery   = RequiredDocument::query();
        $accessibleDepartmentCodes = $user->accessibleDepartmentCodes();

        // 🔒 OFFICE SCOPE: only assigned to this user/department (except super_admin)
        if (! $user->hasRoleSafe('super_admin')) {
            $complyingQuery->whereIn('department_code', $accessibleDepartmentCodes);

            $requiredDocsQuery->whereHas('complyingOffices', fn ($q) =>
                $q->whereIn('department_code', $accessibleDepartmentCodes)
            );
        }

        // 🔒 CONFIDENTIAL CONTROL
        if (! $user->can('ViewConfidential:RequiredDocument')) {
            $complyingQuery->whereHas('requiredDocument', fn ($q) =>
                $q->where('is_confidential', false)
            );

            $requiredDocsQuery->where('is_confidential', false);
        }

        // Case 1: ComplyingOffice exists but status is explicitly -1
        $explicitlyNotComplied = (clone $complyingQuery)->where('status', -1)->count();

        // Case 2: RequiredDocument has NO ComplyingOffice record at all
        $noRecordNotComplied = (clone $requiredDocsQuery)
            ->whereDoesntHave('complyingOffices', function ($q) use ($user, $accessibleDepartmentCodes) {
                //if (!$user->hasRoleSafe('super_admin')) {
                if (! $user->can('ViewAllOffices:RequiredDocument')) {
                    $q->whereIn('department_code', $accessibleDepartmentCodes);
                }
            })->count();

        $notComplied       = $explicitlyNotComplied + $noRecordNotComplied;
        $partiallyComplied = (clone $complyingQuery)->where('status', 0)->count();
        $complied          = (clone $complyingQuery)->where('status', 1)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Document Compliance',
                    'data'  => [$notComplied, $partiallyComplied, $complied],
                    'backgroundColor' => [
                        '#E91E63',
                        '#ff9500',
                        '#1DB584',
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
