<?php

namespace App\Models;

use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    // protected $fillable = ['category'];

    protected $guarded = ['id'];


    public function requiredDocuments()
    {
        return $this->hasMany(RequiredDocument::class, 'document_category_id');
    }

   public function complyingOffices()
    {
        return $this->hasMany(
            ComplyingOffice::class,
            'requirement_id',
            'id'
        );
    }


}
