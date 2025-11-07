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
        Schema::create('action_details', function (Blueprint $table) {
            $table->id();
            $table->string('id_complying_office');
            $table->string('requirement_id');
            $table->string('date');
            $table->string('action');
            $table->string('status')->comment('-1 -not complied; 0 -partially complied; 1 -Completed');
            $table->string('mov_link');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_details');
    }
};
