<?php

namespace App\Models;

use App\Models\RequiredDocument;
use Illuminate\Database\Eloquent\Model;

class RequiredDocumentDivision extends Model
{
    protected $fillable = [
        'required_document_id',
        'department_code',
        'division_code',
    ];

    public function requiredDocument()
    {
        return $this->belongsTo(RequiredDocument::class);
    }
}
