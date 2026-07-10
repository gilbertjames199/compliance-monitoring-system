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
        Schema::create('required_document_divisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('required_document_id');
            $table->string('department_code');
            $table->string('division_code');
            $table->timestamps();

            $table->foreign('required_document_id')
                ->references('id')
                ->on('required_documents')
                ->onDelete('cascade');

            $table->unique(
                ['required_document_id', 'department_code', 'division_code'],
                'unique_req_doc_division'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('required_document_divisions');
    }
};
