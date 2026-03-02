<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelHasRole extends Model
{
    protected $connection = 'mysql';
    protected $table = 'compliance_monitoring.model_has_roles';
    public $timestamps = false; //
    protected $guarded = [];
}
