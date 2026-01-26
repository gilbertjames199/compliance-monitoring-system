<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();

        // Base query for RequiredDocument
        $requiredDocsQuery = RequiredDocument::query();

        // Apply filters based on role
        if ($user->hasRole('superadmin')) {
            // Superadmin sees all
        } 
        elseif ($user->hasRole('department_head')) {
            // Department head sees all documents in their department
            $requiredDocsQuery->whereHas('complyingOffices', function ($query) use ($user) {
                $query->where('department_code', $user->department_code);
            });
        } 
        elseif ($user->hasAnyRole(['AO', 'admin'])) {
            // AO/Admin sees non-confidential documents in their department
            $requiredDocsQuery->whereHas('complyingOffices', function ($query) use ($user) {
                $query->where('department_code', $user->department_code);
            })->where('is_confidential', 0);
        }

        // Status mappings for ComplyingOffice
        $statuses = [
            'Not Complied' => ['status' => '-1', 'color' => 'danger', 'icon' => 'heroicon-m-x-circle'],
            'Partially Complied' => ['status' => '0', 'color' => 'warning', 'icon' => 'heroicon-m-exclamation-circle'],
            'Complied' => ['status' => '1', 'color' => 'success', 'icon' => 'heroicon-m-check-circle'],
        ];

        $stats = [
            // Total Required Documents
            Stat::make('Total Required Documents', $requiredDocsQuery->count())
                ->description('All documents that need compliance based on your role and department')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart($requiredDocsQuery->latest()->take(7)->pluck('id')->toArray()),
        ];

        // Add stats for each ComplyingOffice status
        foreach ($statuses as $label => $options) {
            $query = ComplyingOffice::where('status', $options['status']);

            if (!$user->hasRole('superadmin')) {
                if ($user->hasRole('department_head')) {
                    $query->where('department_code', $user->department_code);
                } 
                elseif ($user->hasAnyRole(['AO', 'admin'])) {
                    $query->where('department_code', $user->department_code)
                          ->whereHas('requiredDocument', function ($q) {
                              $q->where('is_confidential', 0);
                          });
                }
            }

            $stats[] = Stat::make("{$label} Documents", $query->count())
                ->description("Total required documents that are {$label}")
                ->descriptionIcon($options['icon'])
                ->color($options['color'])
                ->chart($query->latest()->take(7)->pluck('id')->toArray());
        }

        return $stats;
    }
}
