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
        // 'required_document_id' is the column on required_documents table
        // 'id' is the matching column on required_documents table
        return $this->belongsTo(RequiredDocument::class, 'required_document_id', 'id');
    }

    public function complying_office()
    {
        // 'requiring_agency' is the column on requirements table
        // 'department_code' is the matching column on offices table
        return $this->belongsTo(ComplyingOffice::class, 'id_complying_office', 'id');
    }
}
