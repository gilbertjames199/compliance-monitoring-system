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
        Schema::table('required_documents', function (Blueprint $table) {
            $table->timestamp('next_occurrence_created_at')->nullable()->after('recurrence_interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('required_documents', function (Blueprint $table) {
            $table->dropColumn('next_occurrence_created_at');
        });
    }
};
