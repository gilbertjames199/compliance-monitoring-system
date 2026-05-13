<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_office_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('department_code');
            $table->timestamps();

            $table->unique(['user_id', 'department_code']);
            $table->index('department_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_office_assignments');
    }
};
