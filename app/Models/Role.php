<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $connection = "mysql";
    protected $table = 'compliance_monitoring.roles';
    protected $guarded = ['id'];

}
