<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionDetail extends Model
{
    protected $connection = "mysql";
    protected $table = 'action_details';
    protected $guarded = ['id'];

    public function requirement()
    {
        // 'requiring_agency' is the column on requirements table
        // 'department_code' is the matching column on offices table
        return $this->belongsTo(RequiredDocument::class, 'requirement_id', 'id');
    }

    public function complying_office()
    {
        // 'requiring_agency' is the column on requirements table
        // 'department_code' is the matching column on offices table
        return $this->belongsTo(ComplyingOffice::class, 'id_complying_office', 'id');
    }
}
