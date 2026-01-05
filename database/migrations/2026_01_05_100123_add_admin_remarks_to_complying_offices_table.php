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
            $table->text('admin_remarks')->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('admin_remarks');
            $table->text('submission_notes')->nullable()->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complying_offices', function (Blueprint $table) {
            $table->dropColumn(['admin_remarks', 'submitted_at', 'submission_notes']);
        });
    }
};
