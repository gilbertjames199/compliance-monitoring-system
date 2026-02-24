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

        $complyingQuery      = ComplyingOffice::query();
        $requiredDocsQuery   = RequiredDocument::query();

        if ($user->hasRoleSafe('super_admin')) {
            // sees all - no filters
        } elseif ($user->hasRoleSafe('department_head')) {
            $complyingQuery->where('department_code', $user->department_code);
            $requiredDocsQuery->whereHas('complyingOffices', fn ($q) =>
                $q->where('department_code', $user->department_code)
            );
        } elseif ($user->hasAnyRole(['AO', 'admin'])) {
            $complyingQuery->where('department_code', $user->department_code)
                        ->whereHas('requiredDocument', fn ($q) =>
                            $q->where('is_confidential', 0)
                        );
            $requiredDocsQuery->where('is_confidential', 0)
                            ->whereHas('complyingOffices', fn ($q) =>
                                $q->where('department_code', $user->department_code)
                            );
        } else {
            $complyingQuery->whereRaw('1 = 0');
            $requiredDocsQuery->whereRaw('1 = 0');
        }

        // Case 1: ComplyingOffice exists but status is explicitly -1
        $explicitlyNotComplied = (clone $complyingQuery)->where('status', -1)->count();

        // Case 2: RequiredDocument has NO ComplyingOffice record at all
        $noRecordNotComplied = (clone $requiredDocsQuery)
            ->whereDoesntHave('complyingOffices', function ($q) use ($user) {
                if (!$user->hasRoleSafe('super_admin')) {
                    $q->where('department_code', $user->department_code);
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