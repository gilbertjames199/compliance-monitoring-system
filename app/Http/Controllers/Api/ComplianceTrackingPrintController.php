<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequiredDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nette\Utils\Json;

class ComplianceTrackingPrintController extends Controller
{
    // public function print(Request $request)
    // {
    //     // Validate request parameters
    //     $request->validate([
    //         'requirement_id' => 'nullable|integer|exists:required_documents,id',
    //     ]);

    //     // If no filters provided, return empty array
    //     if (!$request->filled('requirement_id')) {
    //         return response()->json([]);
    //     }

    //     // Build query and eager load office relationship
    //     $query = RequiredDocument::with(['complyingOffices.office']);

    //     // Filter by specific requirement
    //     if ($request->filled('requirement_id')) {
    //         $query->where('id', $request->requirement_id);
    //     }

    //     // Filter by agency/department
    //     if ($request->filled('agency_id')) {
    //         $query->whereHas('complyingOffices', function ($q) use ($request) {
    //             $q->where('department_code', $request->agency_id);
    //         });
    //     }

    //     $requirements = $query->get();

    //     $result = $requirements->map(function ($req) {

    //         $dueDate = Carbon::parse($req->due_date)->format('Y-m-d');

    //         return [
    //             'requirement_id'    => $req->id,
    //             'requirement_title' => $req->requirement,
    //             'category'          => $req->category?->category,
    //             'agency_type'       => $req->agency_type,
    //             'agency_name'       => $req->agency_name,
    //             'date_from'         => Carbon::parse($req->date_from)->format('Y-m-d'),
    //             'due_date'          => $dueDate,

    //             'offices' => $req->complyingOffices->map(function ($office) use ($dueDate) {

    //                 // Format submitted_at as Y-m-d ONLY (no time)
    //                 // so string compareTo in JasperReports works correctly
    //                 $submittedAt = $office->submitted_at
    //                     ? Carbon::parse($office->submitted_at)->format('Y-m-d')
    //                     : null;

    //                 // Pre-compute status for easier debugging/logging
    //                 // (JasperReports will determine color, this is optional)
    //                 $status = match(true) {
    //                     is_null($submittedAt)         => 'not_submitted',        // ✘ Red
    //                     $submittedAt <= $dueDate      => 'submitted_on_time',    // ✔ Green
    //                     $submittedAt > $dueDate       => 'submitted_late',       // ✔ Red
    //                     default                       => 'not_submitted',
    //                 };

    //                 return [
    //                     'department_code' => $office->department_code,
    //                     'office_name'     => $office->office?->office,
    //                     'submitted_by'    => $office->submitted_by ?? null, // ✅ added
    //                     'submitted_at'    => $submittedAt,                  // ✅ Y-m-d only
    //                     'status'          => $status,                       // optional helper
    //                 ];
    //             }),
    //         ];
    //     });

    //     return response()->json($result);
    // }
    public function print(Request $request)
    {
        // Validate request parameters
        // $request->validate([
        //     'requirement_id' => 'nullable|integer|exists:required_documents,id',
        // ]);

        $data = DB::table('complying_offices')
            ->join('required_documents', 'complying_offices.required_document_id', '=', 'required_documents.id')
            ->join('fms.offices', 'complying_offices.department_code', '=', 'fms.offices.department_code')
            ->join('document_categories', 'required_documents.document_category_id', '=', 'document_categories.id')
            ->select(
                'required_documents.id as requirement_id',
                'required_documents.requirement as requirement_title',
                'document_categories.category',
                'required_documents.agency_type',
                'required_documents.agency_name',
                DB::raw('DATE_FORMAT(required_documents.date_from, "%Y-%m-%d") as date_from'),
                DB::raw('DATE_FORMAT(required_documents.due_date, "%Y-%m-%d") as due_date'),
                'fms.offices.department_code',
                'fms.offices.office as office_name',
                DB::raw('DATE_FORMAT(complying_offices.submitted_at, "%Y-%m-%d") as submitted_at'),
                'complying_offices.submitted_by',
                DB::raw('
                    CASE
                        WHEN complying_offices.submitted_at IS NULL THEN "not_submitted"
                        WHEN DATE(complying_offices.submitted_at) <= DATE(required_documents.due_date) THEN "submitted_on_time"
                        WHEN DATE(complying_offices.submitted_at) > DATE(required_documents.due_date) THEN "submitted_late"
                        ELSE "not_submitted"
                    END as status
                '),

                // totals repeated for each row
                DB::raw('COUNT(*) OVER() as total_offices_required'),
                // DB::raw('SUM(CASE WHEN complying_offices.submitted_at IS NOT NULL THEN 1 ELSE 0 END) OVER() as total_reports_submitted'),
                // DB::raw('SUM(CASE WHEN complying_offices.submitted_at IS NULL THEN 1 ELSE 0 END) OVER() as total_no_submission'),
                

                 // --- New compliance status totals ---
                DB::raw('SUM(CASE WHEN complying_offices.status = "1" THEN 1 ELSE 0 END) OVER() as total_offices_complied'),
                DB::raw('SUM(CASE WHEN complying_offices.status = "0" THEN 1 ELSE 0 END) OVER() as total_offices_partially_complied'),
                DB::raw('SUM(CASE WHEN complying_offices.status = "-1" THEN 1 ELSE 0 END) OVER() as total_offices_not_complied'),
                DB::raw('SUM(CASE WHEN complying_offices.validation_status = "pending_review" THEN 1 ELSE 0 END) OVER() as total_offices_pending_review'),
                DB::raw('SUM(CASE WHEN complying_offices.validation_status = "returned" THEN 1 ELSE 0 END) OVER() as total_offices_returned'),
                DB::raw('SUM(CASE WHEN complying_offices.validation_status = "validated" THEN 1 ELSE 0 END) OVER() as total_offices_validated'),

                DB::raw('CONCAT(
                    ROUND(
                        (SUM(CASE WHEN complying_offices.validation_status = "validated" THEN 1 ELSE 0 END) OVER()
                        / COUNT(*) OVER()) * 100,
                    2),
                "%") as compliance_rate'),

                 // ✅ Validation status and date & time
                'complying_offices.validation_status',
                DB::raw('DATE_FORMAT(complying_offices.validated_at, "%Y-%m-%d %H:%i:%s") as validated_at'),
    

                
            )
            // ->when($request->filled('requirement_id'), function ($query) use ($request) {
            //     $query->where('required_documents.id', $request->requirement_id);
            // })
            ->where('required_documents.id', $request->requirement_id)
            ->get();

            // dd($data);
          
        return response()->json($data);
    }
}