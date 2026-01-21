<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       // Check if required_document_id already exists (from partial migration)
        if (Schema::hasColumn('complying_offices', 'required_document_id')) {
            // Column exists, just need to clean data and add constraint
            
            // Delete orphaned records (where required_document_id doesn't match any required_documents.id)
            DB::statement('
                DELETE FROM complying_offices
                WHERE required_document_id NOT IN (SELECT id FROM required_documents)
            ');
            
            // Also delete records where required_document_id is NULL
            DB::table('complying_offices')
                ->whereNull('required_document_id')
                ->delete();
            
            // Now add the foreign key constraint
            Schema::table('complying_offices', function (Blueprint $table) {
                $table->foreign('required_document_id')
                    ->references('id')
                    ->on('required_documents')
                    ->cascadeOnDelete();
            });
        } else {
            // Column doesn't exist, do the full migration
            Schema::table('complying_offices', function (Blueprint $table) {
                $table->dropColumn('requirement_id');
            });

            Schema::table('complying_offices', function (Blueprint $table) {
                $table->foreignId('required_document_id')
                    ->after('department_code')
                    ->constrained('required_documents')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complying_offices', function (Blueprint $table) {
            $table->dropForeign(['required_document_id']);
            $table->dropColumn('required_document_id');
            
            $table->string('requirement_id')->after('department_code');
        });
    }
};
