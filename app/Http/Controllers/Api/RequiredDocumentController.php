<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequiredDocument;
use Illuminate\Http\Request;

class RequiredDocumentController extends Controller
{
    public function show(Request $request)
    {
        $validated = $request->validate([
            // 'requirement_id'   => 'nullable|integer|exists:required_documents,id',
            'department_code'  => 'nullable|string|exists:complying_offices,department_code',
        ]);

        $query = RequiredDocument::with([
            'category',
            'requiringAgency',
            'complyingOffices' => function ($q) use ($validated) {

                // ✅ Only Not Complied (-1) and Complied (1)
                $q->whereIn('status', [-1, 1]);

                // ✅ Filter by department_code if provided
                if (!empty($validated['department_code'])) {
                    $q->where('department_code', $validated['department_code']);
                }
            }
        ]);

        // ✅ Filter specific requirement if provided
        if (!empty($validated['requirement_id'])) {
            $query->where('id', $validated['requirement_id']);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            return response()->json([]);
        }

        $results = [];

        foreach ($documents as $document) {
            foreach ($document->complyingOffices as $office) {

                $results[] = [
                    'requirement' => $document->requirement,
                    'complying_office' => $office->office?->office ?? null,
                    'requiring_agency' => $document->agency_name ?? null,
                    'document_category' => $document->category?->category ?? null,
                    'compliance_status' => match ((int) $office->status) {
                        -1 => 'Not Complied',
                         0 => 'Partially Complied',
                         1 => 'Complied',
                         default => 'Unknown',
                    },
                    'validation_status' => match ($office->validation_status) {
                        'pending_review' => 'Pending Review',
                        'returned'       => 'Returned',
                        'validated'      => 'Validated',
                        default          => 'Unknown',
                    },
                    'confidentiality' => $document->is_confidential
                        ? 'Confidential'
                        : 'Not Confidential',
                    'start_date' => $document->date_from
                        ? $document->date_from->format('Y-m-d')
                        : null,

                    'deadline' => $document->due_date
                        ? $document->due_date->format('Y-m-d')
                        : null,

                    // 'attachments' => $office->attachments ?? [],
                    'attachments' => $office->attachments
                        ? collect((array) $office->attachments)
                            ->map(fn ($path) => url('storage/' . $path))
                            ->values()
                            ->toArray()
                        : null,
                   

                    // 'attachments' => $office->attachments
                    //     ? collect((array) $office->attachments)
                    //         ->map(fn ($path) => url('storage/' . $path))
                    //         ->implode(', ')
                    //     : null,
                                    
                        ];
            }
        }

        return response()->json($results);
    }
}
