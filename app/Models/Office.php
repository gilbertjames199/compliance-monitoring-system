<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $connection = "mysql2";
    protected $table = 'offices';
    protected $guarded = ['id'];
}
