<?php

namespace App\Models;

use App\Models\RequiredDocument;
use App\Models\Office;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplyingOffice extends Model
{
    // protected $fillable = [
    //     'requirement_id',
    //     'department_code',
    //     'status',
    // ];

    protected $connection = 'mysql';

    protected $guarded = [];


    protected $casts = [
        'attachments' => 'array',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class, 'department_code', 'department_code');
    }

    public function requiredDocument()
    {
        return $this->belongsTo(RequiredDocument::class, 'requirement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
