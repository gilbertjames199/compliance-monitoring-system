<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('required_documents', function (Blueprint $table) {
            $table->id();
            $table->string('requirement');
            $table->string('is_external');
            $table->string('requiring_agency_internal')->nullable()->comment('department code if agency is internal');
            $table->string('agency_name')->comment('name of the requiring agency');
            $table->string('is_confidential');
            $table->string('date_from');
            $table->string('due_date');
            $table->string('year');
            $table->string('is_recurring');
            $table->string('document_category_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('required_documents');
    }
};
