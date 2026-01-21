<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration 
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
                Schema::table('users', function (Blueprint $table) {
                $table->string('username')->nullable()->after('email');
            });

            DB::table('users')->update([
                'username' => DB::raw('email')
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->string('username')->unique()->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('username');
            });
        }
    }
};