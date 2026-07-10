<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDivision extends Model
{
    protected $connection = 'mysql'; 
    
    protected $fillable = [
        'user_id',
        'department_code', 
        'division_code',
    ];
}
