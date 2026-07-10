<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ComplyingOffice;
use App\Models\Office;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    /**
     * Log an audit event for a complying office.
     */
    public static function log(
        string $event,
        ComplyingOffice $office,
        array $old = [],
        array $new = [],
        ?string $remarks = null
    ): void {
       
        // Capture actor information BEFORE it might be lost
        $actor = self::getActorInfo();
       
        // Check if this is a deletion with snapshot data
        $isDeletion = ($event === 'deleted');
        $hasSnapshot = isset($old['fms_office_name']);
       
        if ($isDeletion && $hasSnapshot) {
            // Use snapshot data from the old array (captured before deletion)
            $officeName = self::extractStringValue($old['fms_office_name'] ?? 'Unknown FMS Office');
            $requirementName = self::extractStringValue($old['requirement_name'] ?? 'Deleted Requirement');
            $agencyName = self::extractStringValue($old['agency_name'] ?? 'N/A');
            $oldStatus = $old['status'] ?? null;
            $oldValidationStatus = $old['validation_status'] ?? null;
            $newStatus = null;
            $newValidationStatus = null;
            $requirementId = $old['required_document_id'] ?? null;
        } else {
            // Normal flow for non-deletion events
            $officeName = self::getFmsOfficeName($office);
            $requirementName = self::extractStringValue(
                $office->requiredDocument?->requirement ?? 'Unknown Requirement'
            );
            $agencyName = self::extractStringValue(
                $office->requiredDocument?->agency_name ?? 'N/A'
            );
            $oldStatus = $old['status'] ?? null;
            $oldValidationStatus = $old['validation_status'] ?? null;
            $newStatus = $new['status'] ?? $office->status;
            $newValidationStatus = $new['validation_status'] ?? $office->validation_status;
            $requirementId = $office->requirement_id ?? $office->required_document_id;
        }

        // Prepare data for audit log
        $divisionData = self::resolveDivisionData($isDeletion && $hasSnapshot ? null : $office);

        // Prepare data for audit log
        $data = [
            'event'                 => $event,
            'division_code'         => $divisionData['division_code'],
            'division_name'         => $divisionData['division_name'],
            'user_id'               => $actor['user_id'],
            'acted_by'              => $actor['acted_by'],
            'action_at'             => now(),
            'requirement_id'        => $requirementId,
            'complying_office_id'   => $office->id,
            'requiring_agency_name' => $agencyName,
            'old_status'            => $oldStatus,
            'old_validation_status' => $oldValidationStatus,
            'new_status'            => $newStatus,
            'new_validation_status' => $newValidationStatus,
            'remarks'               => $remarks,
            'office_name'           => $officeName,
            'requirement_name'      => $requirementName,
            'created_at'            => now(),
            'updated_at'            => now(),
        ];
       
        // Create the audit log
        AuditLog::create($data);
    }

    public static function logDocument(
        string $event,
        RequiredDocument $document,
        array $old = [],
        array $new = [],
        ?string $remarks = null
        ): void {
        $actor = self::getActorInfo();

        AuditLog::create([
            'event'                 => $event,
            'division_code'         => null,
            'division_name'         => null,
            'user_id'               => $actor['user_id'],
            'acted_by'              => $actor['acted_by'],
            'action_at'             => now(),
            'requirement_id'        => $document->id,
            'complying_office_id'   => null,
            'requiring_agency_name' => $document->agency_name ?? 'N/A',
            'old_status'            => null,
            'old_validation_status' => null,
            'new_status'            => null,
            'new_validation_status' => null,
            'remarks'               => $remarks,
            'office_name'           => $document->agency_name ?? 'N/A',
            'requirement_name'      => $document->requirement ?? 'Unknown Requirement',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }  
   
    /**
     * Get actor information (who performed the action)
     */
    private static function getActorInfo(): array
    {
        $user = auth()->user();
       
        if ($user) {
            return [
                'user_id' => $user->recid ?? $user->id,
                'acted_by' => $user->fullname ?? $user->name ?? $user->UserName ?? 'Unknown User',
            ];
        }
       
        // Check for impersonation or system user
        $impersonator = session()->get('impersonator_id');
        if ($impersonator) {
            $impersonatorUser = User::find($impersonator);
            if ($impersonatorUser) {
                return [
                    'user_id' => $impersonatorUser->recid ?? $impersonatorUser->id,
                    'acted_by' => $impersonatorUser->fullname ?? $impersonatorUser->name . ' (Impersonating)',
                ];
            }
        }
       
        // System action (cron job, queue, etc.)
        return [
            'user_id' => null,
            'acted_by' => 'System',
        ];
    }

    private static function getFmsOfficeName(ComplyingOffice $office): string
    {
        // ✅ First: use the complying office's own relationship
        if ($office->office) {
            return self::extractStringValue($office->office->office);
        }

        // Fallback: try department_code or other identifiers
        if ($office->department_code) {
            $officeModel = Office::on('mysql2')
                ->where('department_code', $office->department_code)
                ->first();
            if ($officeModel) {
                return self::extractStringValue($officeModel->office);
            }
        }

        return 'Unknown FMS Office';
    }
   
    /**
     * Extract a clean string value from various input types
     */
    private static function extractStringValue($value): string
    {
        if (is_string($value)) {
            return $value;
        }
       
        if (is_array($value)) {
            if (isset($value['office'])) {
                return self::extractStringValue($value['office']);
            }
            if (isset($value['name'])) {
                return self::extractStringValue($value['name']);
            }
            return 'Invalid Array Data';
        }
       
        if (is_object($value)) {
            if (property_exists($value, 'office')) {
                return self::extractStringValue($value->office);
            }
            if (property_exists($value, 'name')) {
                return self::extractStringValue($value->name);
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return 'Object: ' . class_basename($value);
        }
       
        if ($value === null) {
            return 'N/A';
        }
       
        return (string) $value;
    }

    /**
     * Resolve division_code + a display-ready division_name for a ComplyingOffice,
     * for denormalized storage on audit_logs (so history doesn't shift if
     * division names/codes change later).
     */
    public static function resolveDivisionData(?ComplyingOffice $office): array
    {
        if (!$office || blank($office->division_code)) {
            return ['division_code' => null, 'division_name' => null];
        }

        $division = DB::connection('mysql2')
            ->table('fms.divisions')
            ->where('department_code', $office->department_code)
            ->where('division_code', $office->division_code)
            ->first();

        return [
            'division_code' => $office->division_code,
            'division_name' => $division
                ? $division->division_name1 . (!empty($division->division_short_name) ? " ({$division->division_short_name})" : '')
                : $office->division_code,
        ];
    }
}
