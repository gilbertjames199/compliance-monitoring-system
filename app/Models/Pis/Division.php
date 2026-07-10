<?php

namespace App\Models\Pis;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'divisions';
    public $timestamps = false;

    protected $primaryKey = 'division_code';
    public $incrementing = false;
    protected $keyType = 'string';
}
