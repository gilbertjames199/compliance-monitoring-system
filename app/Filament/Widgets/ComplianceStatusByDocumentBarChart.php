<?php

namespace App\Filament\Widgets;

use App\Models\RequiredDocument;
use App\Models\ComplyingOffice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class ComplianceStatusByDocumentBarChart extends ChartWidget implements HasForms
{
    use InteractsWithForms;

    protected ?string $heading = 'Compliance Status by Required Document';
    protected static ?int $sort = 3;

    public ?string $selectedDocument = null;

    // ✅ Add filter form to widget
    protected function getFormSchema(): array
    {
        $user = Auth::user();
        $office = $user?->office;

        return [
            Select::make('selectedDocument')
                ->label('Select Required Document')
                ->placeholder('Choose a document...')
                ->options(function () use ($office) {
                    if (!$office) {
                        return [];
                    }

                    // Match by agency_name from the office
                    return RequiredDocument::where('agency_name', $office->office)
                        ->orderBy('requirement')
                        ->pluck('requirement', 'id')
                        ->toArray();
                })
                ->reactive()
                ->afterStateUpdated(fn () => $this->chartUpdateDelay(500))
                ->helperText('Select a document to see compliance status of offices'),
        ];
    }

    protected function getData(): array
    {
        $requirementId = $this->selectedDocument;

        // Return empty chart if no document selected
        if (!$requirementId) {
            return [
                'datasets' => [
                    [
                        'label' => 'Number of Offices',
                        'data' => [0, 0, 0],
                        'backgroundColor' => [
                            'rgb(255, 99, 132)',
                            'rgb(251, 191, 36)',
                            'rgb(34, 197, 94)',
                        ],
                    ],
                ],
                'labels' => ['Not Complied', 'Partially Complied', 'Complied'],
            ];
        }

        // Get counts for each status
        $notCompliedCount = ComplyingOffice::where('requirement_id', $requirementId)
            ->where('status', -1)
            ->count();

        $partiallyCompliedCount = ComplyingOffice::where('requirement_id', $requirementId)
            ->where('status', 0)
            ->count();

        $compliedCount = ComplyingOffice::where('requirement_id', $requirementId)
            ->where('status', 1)
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Number of Offices',
                    'data' => [
                        $notCompliedCount,
                        $partiallyCompliedCount,
                        $compliedCount,
                    ],
                    'backgroundColor' => [
                        'rgb(255, 99, 132)',   // red - Not Complied
                        'rgb(251, 191, 36)',   // amber - Partially Complied
                        'rgb(34, 197, 94)',    // green - Complied
                    ],
                    'borderColor' => [
                        'rgb(255, 99, 132)',
                        'rgb(251, 191, 36)',
                        'rgb(34, 197, 94)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => [
                'Not Complied',
                'Partially Complied',
                'Complied',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    // ✅ Make chart clickable - navigate to filtered office list
    protected function getOptions(): array
    {
        return [
            'onClick' => 'function(event) {
                const datasetIndex = event.native.datasetIndex ?? 0;
                const index = event.native.dataIndex ?? 0;
                const statusMap = [-1, 0, 1]; // Not Complied, Partially Complied, Complied
                const status = statusMap[index];
                const requirementId = ' . json_encode($this->selectedDocument) . ';
                
                if (!requirementId) return;

                // Navigate to ComplyingOffices resource with filters
                const baseUrl = window.location.origin + "/admin/complying-offices";
                const filterParams = new URLSearchParams({
                    "tableFilters[status]": status,
                    "tableFilters[requirement_id]": requirementId
                });
                
                window.location.href = baseUrl + "?" + filterParams.toString();
            }',
            'responsive' => true,
            'maintainAspectRatio' => true,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}

