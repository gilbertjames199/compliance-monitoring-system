<?php

namespace App\Observers;

use App\Models\ComplyingOffice;
use App\Services\AuditLogger;

class ComplyingOfficeObserver
{
    public function created(ComplyingOffice $office): void
    {
        $status = (string) $office->status;
        $isSubmission = in_array($status, ['0', '1']);
       
        if ($isSubmission) {
            AuditLogger::log(
                'submitted - ' . $this->statusLabel($office->status),
                $office,
                [],
                [
                    'status' => $office->status,
                    'validation_status' => $office->validation_status,
                ],
                $office->admin_remarks
            );
        } else {
            AuditLogger::log(
                'added office',
                $office,
                [],
                [
                    'status' => $office->status,
                    'validation_status' => $office->validation_status,
                ],
                $office->admin_remarks
            );
        }
    }

    public function updated(ComplyingOffice $office): void
    {
        $old = [
            'status' => $office->getOriginal('status'),
            'validation_status' => $office->getOriginal('validation_status'),
        ];

        $new = [
            'status' => $office->status,
            'validation_status' => $office->validation_status,
        ];

        $statusSame = (string)$old['status'] === (string)$new['status'];
        $validationSame = $old['validation_status'] === $new['validation_status'];
        $isResubmission = $office->was_returned && in_array((string)$new['status'], ['0', '1']);
        // $isSubmissionAttempt = in_array((string)$new['status'], ['0', '1']);
        $isSubmissionAttempt = in_array((string)$new['status'], ['0', '1']) 
            && $new['validation_status'] !== 'validated';

        if ($statusSame && $validationSame && !$isResubmission && !$isSubmissionAttempt) {
            return;
        }

        if ($office->wasRecentlyCreated) {
            return;
        }

        if ($old['status'] === null && in_array((string)$new['status'], ['0', '1'])) {
            return;
        }

        // ✅ Resolve event FIRST before mutating was_returned
        $eventName = $this->resolveEvent($old, $new, $office);

        // ✅ THEN update was_returned AFTER resolving the event
        // Also reset was_returned when resubmitted so next return cycle works
        if ($new['validation_status'] === 'returned' && !$office->was_returned) {
            $office->updateQuietly(['was_returned' => true]);
        }

        if ($eventName) {
            AuditLogger::log(
                $eventName,
                $office,
                $old,
                $new,
                $office->admin_remarks
            );
        }
    }

    public function deleted(ComplyingOffice $office): void
    {
        $snapshot = $office->getSnapshotData();
       
        if (!empty($snapshot)) {
            if (isset($snapshot['fms_office_name']) && !is_string($snapshot['fms_office_name'])) {
                if (is_array($snapshot['fms_office_name']) && isset($snapshot['fms_office_name']['office'])) {
                    $snapshot['fms_office_name'] = (string) $snapshot['fms_office_name']['office'];
                } elseif (is_object($snapshot['fms_office_name']) && property_exists($snapshot['fms_office_name'], 'office')) {
                    $snapshot['fms_office_name'] = (string) $snapshot['fms_office_name']->office;
                } else {
                    $snapshot['fms_office_name'] = 'Unknown FMS Office';
                }
            }
           
            if (!isset($snapshot['requirement_id']) && isset($snapshot['required_document_id'])) {
                $snapshot['requirement_id'] = $snapshot['required_document_id'];
            }
           
            $user = auth()->user();
            $snapshot['actor_user_id'] = $user?->recid ?? $user?->id;
            $snapshot['actor_acted_by_id'] = $user?->recid ?? $user?->id;
           
            AuditLogger::log(
                'deleted',
                $office,
                $snapshot,
                [],
                $snapshot['admin_remarks'] ?? $office->admin_remarks
            );
        } else {
            $user = auth()->user();
            $userId = $user?->recid ?? $user?->id;
           
            AuditLogger::log(
                'deleted',
                $office,
                [
                    'status' => $office->status,
                    'validation_status' => $office->validation_status,
                    'fms_office_name' => 'Unknown FMS Office',
                    'agency_name' => $office->requiredDocument?->agency_name ?? 'N/A',
                    'requirement_name' => $office->requiredDocument?->requirement ?? 'Deleted Requirement',
                    'requirement_id' => $office->requirement_id ?? $office->required_document_id,
                    'actor_user_id' => $userId,
                    'actor_acted_by_id' => $userId,
                ],
                [],
                $office->admin_remarks
            );
        }
    }

    public function restored(ComplyingOffice $office): void
    {
        // optional
    }

    public function forceDeleted(ComplyingOffice $office): void
    {
        // optional
    }

   
    private function resolveEvent(array $old, array $new, ComplyingOffice $office): ?string
    {
        $oldStatus = (string) ($old['status'] ?? '');
        $newStatus = (string) ($new['status'] ?? '');
        $validationChanging = $old['validation_status'] !== $new['validation_status'];

        // Priority 1: Resubmission after return
        if (
            $office->was_returned &&
            in_array($newStatus, ['0', '1']) &&
            !in_array($new['validation_status'], ['returned', 'validated'])
        ) {
            // ✅ Only skip if same status AND it's Complied (1)
            if ($oldStatus === $newStatus && $newStatus === '1') return null;
            return 'resubmitted - ' . $this->statusLabel($newStatus);
        }

        // Priority 2: Admin validation actions
        if ($validationChanging) {
            if ($new['validation_status'] === 'validated') return 'validated';
            if ($new['validation_status'] === 'returned')  return 'returned';
            return null;
        }

        // Priority 3: Normal submission
        if (in_array($newStatus, ['0', '1']) && $new['validation_status'] !== 'validated') {
            // ✅ Only skip if same status AND it's Complied (1)
            if ($oldStatus === $newStatus && $newStatus === '1') return null;
            return 'submitted - ' . $this->statusLabel($newStatus);
        }

        return null;
    }

    private function statusLabel(mixed $status): string
    {
        return match ((string)$status) {
            '0' => 'Partially Complied',
            '1' => 'Complied',
            default => 'Not Complied',
        };
    }
}