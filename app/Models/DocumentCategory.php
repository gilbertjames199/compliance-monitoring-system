<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    protected $fillable = ['category'];

    public function requiredDocuments()
    {
        return $this->hasMany(RequiredDocument::class, 'document_category_id');
    }
}
