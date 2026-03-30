<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $connection = 'mysql';
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Requirement being acted on (may be deleted)
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(RequiredDocument::class, 'requirement_id');
    }

    /**
     * Office involved in the action (may be deleted)
     */
    public function complyingOffice(): BelongsTo
    {
        return $this->belongsTo(ComplyingOffice::class, 'complying_office_id');
    }

    /**
     * User who owns the record (optional context)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Actual actor (who performed the action)
     */
    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /**
     * Accessor: get the office name from stored column (NOT from relationship)
     * This is the FMS office name from the required document
     */
    public function getOfficeNameAttribute(): string
    {
        // Use the stored column directly, not the relationship
        return $this->attributes['office_name'] ?? 'Unknown Office';
    }

    /**
     * Accessor: get the requirement name from stored column
     */
    public function getRequirementNameAttribute(): string
    {
        return $this->attributes['requirement_name'] ?? 'Unknown Requirement';
    }

    /**
     * Accessor: get the requiring agency name from stored column
     */
    public function getRequiringAgencyNameAttribute(): string
    {
        return $this->attributes['requiring_agency_name'] ?? 'N/A';
    }

    /**
     * Mutator: ensure office_name is always stored as a string, not JSON
     */
    public function setOfficeNameAttribute($value): void
    {
        if (is_array($value)) {
            // If it's an array with an 'office' key, extract that
            if (isset($value['office'])) {
                $this->attributes['office_name'] = (string) $value['office'];
            } else {
                $this->attributes['office_name'] = 'Invalid Data';
            }
        } elseif (is_object($value)) {
            // If it's an object with an 'office' property
            if (property_exists($value, 'office')) {
                $this->attributes['office_name'] = (string) $value->office;
            } elseif (method_exists($value, '__toString')) {
                $this->attributes['office_name'] = (string) $value;
            } else {
                $this->attributes['office_name'] = 'Object: ' . class_basename($value);
            }
        } else {
            $this->attributes['office_name'] = (string) $value;
        }
    }

    /**
     * Mutator: ensure requirement_name is always a string
     */
    public function setRequirementNameAttribute($value): void
    {
        $this->attributes['requirement_name'] = is_string($value) ? $value : (string) $value;
    }

    /**
     * Mutator: ensure requiring_agency_name is always a string
     */
    public function setRequiringAgencyNameAttribute($value): void
    {
        $this->attributes['requiring_agency_name'] = is_string($value) ? $value : (string) $value;
    }

    /**
     * Old accessor for backward compatibility - use stored column
     */
    public function getOldStatusAttribute($value): ?string
    {
        return $value;
    }

    /**
     * New accessor for backward compatibility - use stored column
     */
    public function getNewStatusAttribute($value): ?string
    {
        return $value;
    }
}
