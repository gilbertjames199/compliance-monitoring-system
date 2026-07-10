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

        // Divisions assigned to this user (same helper used in ComplianceOverviewTable / StatsOverview)
        $userDivisionCodes = collect(
            method_exists($user, 'divisionCodes') ? $user->divisionCodes() : []
        )->filter()->values();

        // 🔒 OFFICE SCOPE: only assigned to this user/department (except super_admin)
        if (! $user->hasRoleSafe('super_admin')) {
            $complyingQuery->whereIn('department_code', $accessibleDepartmentCodes);

            $requiredDocsQuery->whereHas('complyingOffices', fn ($q) =>
                $q->whereIn('department_code', $accessibleDepartmentCodes)
            );

            // 🔒 DIVISION SCOPING
            // Only count complying-office rows for non-division-tracked requirements,
            // or rows whose division matches one this user is assigned to.
            $complyingQuery->where(function ($q) use ($userDivisionCodes) {
                $q->whereHas('requiredDocument', fn ($rq) =>
                    $rq->where('requires_division_tracking', false)
                        ->orWhereNull('requires_division_tracking')
                );

                if ($userDivisionCodes->isNotEmpty()) {
                    $q->orWhereIn('division_code', $userDivisionCodes->toArray());
                }
            });

            // Same idea for the RequiredDocument side: a division-tracked document
            // only counts for this user if at least one of its complying offices
            // matches a division they're assigned to.
            $requiredDocsQuery->where(function ($q) use ($userDivisionCodes) {
                $q->where('requires_division_tracking', false)
                    ->orWhereNull('requires_division_tracking');

                if ($userDivisionCodes->isNotEmpty()) {
                    $q->orWhereHas('complyingOffices', function ($q2) use ($userDivisionCodes) {
                        $q2->whereIn('division_code', $userDivisionCodes->toArray());
                    });
                }
            });
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
            ->whereDoesntHave('complyingOffices', function ($q) use ($user, $accessibleDepartmentCodes, $userDivisionCodes) {
                //if (!$user->hasRoleSafe('super_admin')) {
                if (! $user->can('ViewAllOffices:RequiredDocument')) {
                    $q->whereIn('department_code', $accessibleDepartmentCodes);

                    if ($userDivisionCodes->isNotEmpty()) {
                        $q->where(function ($q2) use ($userDivisionCodes) {
                            $q2->whereNull('division_code')
                                ->orWhereIn('division_code', $userDivisionCodes->toArray());
                        });
                    }
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