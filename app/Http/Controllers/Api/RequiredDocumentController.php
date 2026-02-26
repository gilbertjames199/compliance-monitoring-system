<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequiredDocument;
use Illuminate\Http\Request;

class RequiredDocumentController extends Controller
{
    public function show(Request $request)
    {
        if (!$request->has('department_code')) {
            return response()->json([]);
        }

        $validated = $request->validate([
            'department_code' => 'required|string|exists:complying_offices,department_code',
            'per_page'        => 'sometimes|integer|min:1|max:100',
            'page'            => 'sometimes|integer|min:1',
        ]);

        $perPage = $validated['per_page'] ?? 10;

        $documents = RequiredDocument::whereHas('complyingOffices', function ($q) use ($validated) {
            $q->whereIn('status', [-1, 1])
            ->where('department_code', $validated['department_code']);
            })
        ->with([
            'category',
            'requiringAgency',
            'complyingOffices' => function ($q) use ($validated) {
                $q->whereIn('status', [-1, 1])
                ->where('department_code', $validated['department_code']);
            },
            'complyingOffices.office'
        ])
        ->paginate($perPage);

        $results = [];

        foreach ($documents as $document) {
            foreach ($document->complyingOffices as $office) {
                $results[] = [
                    'requirement'       => $document->requirement,
                    'complying_office'  => $office->office?->office ?? null,
                    'requiring_agency'  => $document->agency_name ?? null,
                    'document_category' => $document->category?->category ?? null,
                    'compliance_status' => match ((int) $office->status) {
                        -1      => 'Not Complied',
                        1       => 'Complied',
                        default => 'Unknown',
                    },
                    'validation_status' => match ($office->validation_status) {
                        'pending_review' => 'Pending Review',
                        'returned'       => 'Returned',
                        'validated'      => 'Validated',
                        default          => 'Unknown',
                    },
                    'confidentiality' => $document->is_confidential ? 'Confidential' : 'Not Confidential',
                    'start_date'      => $document->date_from?->format('Y-m-d'),
                    'deadline'        => $document->due_date?->format('Y-m-d'),
                    'attachments'     => $office->attachments
                        ? collect((array) $office->attachments)
                            ->map(fn($path) => url('storage/' . $path))
                            ->values()
                            ->toArray()
                        : [],
                ];
            }
        }

        return response()->json([
            'data' => $results,
            'meta' => [
                'current_page' => $documents->currentPage(),
                'per_page'     => $documents->perPage(),
                'total'        => $documents->total(),
                'last_page'    => $documents->lastPage(),
                'from'         => $documents->firstItem(),
                'to'           => $documents->lastItem(),
            ],
            'links' => [
                'first' => $documents->url(1),
                'last'  => $documents->url($documents->lastPage()),
                'prev'  => $documents->previousPageUrl(),
                'next'  => $documents->nextPageUrl(),
            ],
        ]);
    }
}