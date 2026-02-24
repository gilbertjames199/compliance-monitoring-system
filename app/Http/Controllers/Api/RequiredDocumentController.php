<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequiredDocument;
use Illuminate\Http\Request;

class RequiredDocumentController extends Controller
{
    public function show(Request $request)
    {
        // ✅ Return empty if no filters provided
        if (
            !$request->has('requirement_id') &&
            !$request->has('department_code')
        ) {
            return response()->json([]);
        }

        // ✅ Validate input
        $validated = $request->validate([
            'requirement_id'   => 'nullable|integer|exists:required_documents,id',
            'department_code'  => 'nullable|string|exists:complying_offices,department_code',
        ]);

        // ✅ Query with eager loading
        $query = RequiredDocument::with([
            'category',
            'requiringAgency',
            'complyingOffices' => function ($q) use ($validated) {

                // Only Not Complied and Complied
                $q->whereIn('status', [-1, 1]);

                // Filter department if provided
                if (!empty($validated['department_code'])) {
                    $q->where('department_code', $validated['department_code']);
                }
            },
            'complyingOffices.office'
        ]);

        // ✅ Filter requirement if provided
        if (!empty($validated['requirement_id'])) {
            $query->where('id', $validated['requirement_id']);
        }

        $documents = $query->get();

        $results = [];

        foreach ($documents as $document) {

            foreach ($document->complyingOffices as $office) {

                $results[] = [

                    'requirement' => $document->requirement,

                    'complying_office' =>
                        $office->office?->office ?? null,

                    'requiring_agency' =>
                        $document->agency_name ?? null,

                    'document_category' =>
                        $document->category?->category ?? null,

                    'compliance_status' => match ((int) $office->status) {
                        -1 => 'Not Complied',
                         1 => 'Complied',
                         default => 'Unknown',
                    },

                    'validation_status' => match ($office->validation_status) {
                        'pending_review' => 'Pending Review',
                        'returned'       => 'Returned',
                        'validated'      => 'Validated',
                        default          => 'Unknown',
                    },

                    'confidentiality' =>
                        $document->is_confidential
                            ? 'Confidential'
                            : 'Not Confidential',

                    'start_date' =>
                        $document->date_from
                            ? $document->date_from->format('Y-m-d')
                            : null,

                    'deadline' =>
                        $document->due_date
                            ? $document->due_date->format('Y-m-d')
                            : null,

                    // ✅ Attachment URLs
                    'attachments' =>
                        $office->attachments
                            ? collect((array) $office->attachments)
                                ->map(fn ($path) => url('storage/' . $path))
                                ->values()
                                ->toArray()
                            : [],
                ];
            }
        }

        return response()->json($results);
    }
}