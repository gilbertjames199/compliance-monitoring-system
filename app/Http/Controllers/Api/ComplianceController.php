<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplyingOffice;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function index()
    {
        // Only Not Complied (-1) and Partially Complied (0)
        $offices = ComplyingOffice::with([
            'requiredDocument',  // relationship to get Requirement & Category
            'office'         // relationship to get Requiring Agency
        ])
        ->whereIn('status', [-1, 0])
        ->get();

        // Transform the data
        $data = $offices->map(function ($office) {
            $isConfidential = (bool) ($requiredDoc?->is_confidential ?? false);

            return [
                'Requirement'  => $office->requiredDocument?->requirement ?? null,
                'Complying Office' => $office->office?->office ?? null, // name of the office
                'Requiring Agency' => $office->requiredDocument?->agency_name ?? null, // agency
                'Category'  => $office->requiredDocument?->category?->category ?? null,
                'Compliance Status'  => match ((int)$office->status) {
                    -1 => 'Not Complied',
                     0 => 'Partially Complied',
                     1 => 'Complied',
                     default => 'Unknown',
                },
                 'Validation Status'  => match ($office->validation_status) {
                    'pending_review' => 'Pending Review',
                     'returned' => 'Returned',
                     'validated' => 'Validated',
                     default => 'Unknown',
                },

                'Confidentiality' => $office->requiredDocument?->is_confidential? 'Confidential': 'Not Confidential',
                
                'Start Date' => $office->requiredDocument?->date_from
                    ? $office->requiredDocument->date_from->format('Y-m-d')
                    : null,

                'Deadline' => $office->requiredDocument?->due_date
                    ? $office->requiredDocument->due_date->format('Y-m-d')
                    : null,

                'attachments' => $office->attachments ?? [],
            ];
        });

        return response()->json($data);
    }

}
