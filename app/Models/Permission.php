<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $connection = "mysql";
    protected $table = 'compliance_monitoring.permissions';
    // protected $guarded = ['id'];

}
