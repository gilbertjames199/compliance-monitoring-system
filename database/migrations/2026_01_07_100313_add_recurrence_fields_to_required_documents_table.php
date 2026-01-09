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
            $table->string('recurrence_type')->nullable()->after('is_recurring');
            $table->integer('recurrence_interval')->nullable()->after('recurrence_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('required_documents', function (Blueprint $table) {
            $table->dropColumn(['recurrence_type', 'recurrence_interval']);
        });
    }
};
