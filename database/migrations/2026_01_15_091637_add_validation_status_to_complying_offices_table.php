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
        Schema::table('complying_offices', function (Blueprint $table) {
             $table->string('validation_status')->default('pending_review')->after('submission_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complying_offices', function (Blueprint $table) {
             $table->dropColumn('validation_status');
        });
    }
};
