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
            $table->string('submitted_by')->nullable()->after('admin_remarks');
            $table->string('validated_by')->nullable()->after('validation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complying_offices', function (Blueprint $table) {
            $table->dropColumn(['submitted_by', 'validated_by']);
        });
    }
};
