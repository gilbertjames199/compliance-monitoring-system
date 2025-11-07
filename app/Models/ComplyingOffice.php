<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplyingOffice extends Model
{
    protected $fillable = [
        'department_code',
        'requirement_id',
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
