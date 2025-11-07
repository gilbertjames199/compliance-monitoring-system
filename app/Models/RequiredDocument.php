<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequiredDocument extends Model
{
    // protected $fillable = [
    //     'requiring_agency_internal',
    //     'requirement',
    //     'date_created',
    //     'due_date',
    //     'year',
    //     'is_confidential',
    //     'is_external',
    //     'is_recurring',
    //     'document_category_id',
    // ];
    protected $guarded=['id'];
    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id','id');
    }

    public function complyingOffices()
    {
        return $this->hasMany(ComplyingOffice::class, 'requirement_id', 'id');
    }
}
