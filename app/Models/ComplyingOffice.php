<?php

namespace App\Models;

use App\Models\RequiredDocument;
use App\Models\Office;
use Illuminate\Database\Eloquent\Model;

class ComplyingOffice extends Model
{
    protected $fillable = [
        'requirement_id',
        'department_code',
        'status',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class, 'department_code', 'department_code');
    }

    public function requiredDocument()
    {
        return $this->belongsTo(RequiredDocument::class, 'requirement_id');
    }
}
