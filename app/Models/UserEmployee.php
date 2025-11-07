<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserEmployee extends Model
{
    protected $connection = "mysql3";
    protected $table = 'user_employees';
    protected $guarded = ['id'];

    public function office()
    {
        return $this->belongsTo(Office::class, 'department_code', 'department_code');
    }
}
