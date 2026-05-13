<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOfficeAssignment extends Model
{
    protected $connection = 'mysql';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'recid');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'department_code', 'department_code');
    }
}
