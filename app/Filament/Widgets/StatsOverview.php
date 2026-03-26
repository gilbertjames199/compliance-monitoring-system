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

        // // Apply filters based on role
        // if ($user->hasRoleSafe('super_admin')) {
        //     // Superadmin sees all
        // } 
        // elseif ($user->hasRoleSafe('department_head')) {
        //     $requiredDocsQuery->whereHas('complyingOffices', fn ($q) => 
        //         $q->where('department_code', $user->department_code)
        //     );
        // } 
        // elseif ($user->hasRoleSafe('AO', 'admin')) {
        //     $requiredDocsQuery->whereHas('complyingOffices', fn ($q) => 
        //         $q->where('department_code', $user->department_code)
        //     )->where('is_confidential', 0);
        // } 
        // else {
        //     $requiredDocsQuery->whereRaw('1 = 0');
        // }
        // 🔒 OFFICE SCOPE
        if (! $user->hasRoleSafe('super_admin')) {
            $requiredDocsQuery->whereHas('complyingOffices', fn ($q) =>
                $q->where('department_code', $user->department_code)
            );
        }
        // 🔒 CONFIDENTIAL CONTROL
        if (! $user->can('ViewConfidential:RequiredDocument')) {
            $requiredDocsQuery->where('is_confidential', false);
        }

        // Status mappings for ComplyingOffice
        $statuses = [
            'Not Complied'     => ['status' => '-1', 'color' => 'danger',  'icon' => 'heroicon-m-x-circle'],
            'Partially Complied' => ['status' => '0',  'color' => 'warning', 'icon' => 'heroicon-m-exclamation-circle'],
            'Complied'         => ['status' => '1',  'color' => 'success', 'icon' => 'heroicon-m-check-circle'],
        ];

        $stats = [
            Stat::make('Total Required Documents', $requiredDocsQuery->count())
                ->description('All documents that need compliance based on your role and department')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart($requiredDocsQuery->latest()->take(7)->pluck('id')->toArray()),
        ];

        foreach ($statuses as $label => $options) {
            $query = ComplyingOffice::where('status', $options['status']);

            // if ($user->hasRoleSafe('super_admin')) {
            //     // Sees all - no filters
            // } 
            // elseif ($user->hasRoleSafe('department_head')) {
            //     $query->where('department_code', $user->department_code);
            // } 
            // elseif ($user->hasRoleSafe('AO', 'admin')) {
            //     $query->where('department_code', $user->department_code)
            //           ->whereHas('requiredDocument', fn ($q) => 
            //               $q->where('is_confidential', 0)
            //           );
            // } 
            // else {
            //     $query->whereRaw('1 = 0');
            // }
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

            $stats[] = Stat::make("{$label} Documents", $query->count())
                ->description("Total required documents that are {$label}")
                ->descriptionIcon($options['icon'])
                ->color($options['color'])
                ->chart($query->latest()->take(7)->pluck('id')->toArray());
        }

        return $stats;
    }
}