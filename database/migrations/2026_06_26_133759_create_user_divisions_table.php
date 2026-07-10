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
        Schema::create('user_divisions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id'); // recid from systemusers
            $table->string('department_code');
            $table->string('division_code');
            $table->timestamps();

            $table->unique(['user_id', 'division_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_divisions');
    }
};
