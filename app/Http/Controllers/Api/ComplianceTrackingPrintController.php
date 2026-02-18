<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequiredDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ComplianceTrackingPrintController extends Controller
{
    public function print(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'requirement_id' => 'nullable|integer|exists:required_documents,id',
        ]);

        // If no filters provided, return empty array
        if (!$request->filled('requirement_id')) {
            return response()->json([]);
        }

        // Build query and eager load office relationship
        $query = RequiredDocument::with(['complyingOffices.office']);

        // Filter by specific requirement
        if ($request->filled('requirement_id')) {
            $query->where('id', $request->requirement_id);
        }

        // Filter by agency/department
        if ($request->filled('agency_id')) {
            $query->whereHas('complyingOffices', function ($q) use ($request) {
                $q->where('department_code', $request->agency_id);
            });
        }

        $requirements = $query->get();

        $result = $requirements->map(function ($req) {

            $dueDate = Carbon::parse($req->due_date)->format('Y-m-d');

            return [
                'requirement_id'    => $req->id,
                'requirement_title' => $req->requirement,
                'category'          => $req->category?->category,
                'agency_type'       => $req->agency_type,
                'agency_name'       => $req->agency_name,
                'date_from'         => Carbon::parse($req->date_from)->format('Y-m-d'),
                'due_date'          => $dueDate,

                'offices' => $req->complyingOffices->map(function ($office) use ($dueDate) {

                    // Format submitted_at as Y-m-d ONLY (no time)
                    // so string compareTo in JasperReports works correctly
                    $submittedAt = $office->submitted_at
                        ? Carbon::parse($office->submitted_at)->format('Y-m-d')
                        : null;

                    // Pre-compute status for easier debugging/logging
                    // (JasperReports will determine color, this is optional)
                    $status = match(true) {
                        is_null($submittedAt)         => 'not_submitted',        // ✘ Red
                        $submittedAt <= $dueDate      => 'submitted_on_time',    // ✔ Green
                        $submittedAt > $dueDate       => 'submitted_late',       // ✔ Red
                        default                       => 'not_submitted',
                    };

                    return [
                        'department_code' => $office->department_code,
                        'office_name'     => $office->office?->office,
                        'submitted_by'    => $office->submitted_by ?? null, // ✅ added
                        'submitted_at'    => $submittedAt,                  // ✅ Y-m-d only
                        'status'          => $status,                       // optional helper
                    ];
                }),
            ];
        });

        return response()->json($result);
    }
}