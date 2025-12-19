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
            $table->string('agency_type');
            // $table->string('requiring_agency_internal')->nullable()->comment('department code if agency is internal');
            $table->string('agency_name')->comment('name of the requiring agency');
            // $table->string('is_confidential');
            // $table->string('date_from');
            // $table->string('due_date');
            $table->date('date_from');
            $table->date('due_date');
            $table->year('year');
            // $table->string('year');
            // $table->string('is_recurring');
            $table->boolean('is_confidential')->default(false);
            $table->boolean('is_recurring')->default(false);

            // $table->string('document_category_id');
            $table->foreignId('document_category_id')
                  ->constrained('document_categories') // references document_categories.id
                  ->onDelete('cascade');
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
