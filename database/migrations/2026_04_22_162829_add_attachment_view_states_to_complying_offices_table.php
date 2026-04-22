<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complying_offices', function (Blueprint $table) {
            $table->json('attachment_view_states')->nullable()->after('attachment_annotations');
        });
    }

    public function down(): void
    {
        Schema::table('complying_offices', function (Blueprint $table) {
            $table->dropColumn('attachment_view_states');
        });
    }
};
