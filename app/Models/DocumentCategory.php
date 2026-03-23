<?php

namespace App\Models;

use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentCategory extends Model
{
    // protected $fillable = ['category'];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->created_by ??= auth()->id();
        });

        static::updating(function ($model) {
            $model->created_by = auth()->id(); // updates to whoever last edited
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'recid');
    }

    public function requiredDocuments()
    {
        return $this->hasMany(RequiredDocument::class, 'document_category_id');
    }

   public function complyingOffices()
    {
        return $this->hasMany(
            ComplyingOffice::class,
            'required_document_id',
            'id'
        );
    }

    


}
