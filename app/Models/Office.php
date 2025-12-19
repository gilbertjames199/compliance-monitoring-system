<?php

namespace App\Models;

use App\Models\RequiredDocument;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $connection = "mysql2";
    protected $table = 'fms.offices';
    protected $guarded = ['id'];

     public function requirements()
    {
        return $this->hasMany(RequiredDocument::class);
    }

    public function office()
    {
        return $this->belongsTo(office::class);
    }
}
