<?php

namespace App\Filament\Widgets;

use App\Models\ComplyingOffice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompliancePerOfficeBarChart extends ChartWidget
{
    protected ?string $heading = 'Compliance Status per Office';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $user = Auth::user();

        // Base query for ComplyingOffice
        $baseQuery = ComplyingOffice::query();

        // Apply filters based on role
        if (!$user->hasRole('superadmin')) {
            if ($user->hasRole('department_head')) {
                $baseQuery->where('complying_offices.department_code', $user->department_code);
            } elseif ($user->hasAnyRole(['ao', 'admin'])) {
                $baseQuery->where('complying_offices.department_code', $user->department_code)
                          ->whereHas('requiredDocument', function ($q) {
                              $q->where('is_confidential', 0);
                          });
            }
        }

        // Get offices and their compliance status counts
        $officeData = $baseQuery
            ->select('office_id', DB::raw('COUNT(*) as total'))
            ->groupBy('office_id')
            ->with('office')
            ->get();

        // Prepare data for chart
        $officeNames = [];
        $notCompliedCounts = [];
        $partiallyCompliedCounts = [];
        $compliedCounts = [];

        foreach ($officeData as $office) {
            $officeNames[] = $office->office->office ?? 'Unknown';

            // Count by status for this office
            $notComplied = ComplyingOffice::where('office_id', $office->office_id)
                ->where('status', -1)
                ->count();
            
            $partiallyComplied = ComplyingOffice::where('office_id', $office->office_id)
                ->where('status', 0)
                ->count();
            
            $complied = ComplyingOffice::where('office_id', $office->office_id)
                ->where('status', 1)
                ->count();

            $notCompliedCounts[] = $notComplied;
            $partiallyCompliedCounts[] = $partiallyComplied;
            $compliedCounts[] = $complied;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Not Complied',
                    'data' => $notCompliedCounts,
                    'backgroundColor' => 'rgb(255, 99, 132)',
                ],
                [
                    'label' => 'Partially Complied',
                    'data' => $partiallyCompliedCounts,
                    'backgroundColor' => 'rgb(251, 191, 36)',
                ],
                [
                    'label' => 'Complied',
                    'data' => $compliedCounts,
                    'backgroundColor' => 'rgb(34, 197, 94)',
                ],
            ],
            'labels' => $officeNames,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
