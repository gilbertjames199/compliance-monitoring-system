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
        Schema::create('complying_offices', function (Blueprint $table) {
            $table->id();
            $table->string('department_code');
            $table->string('requirement_id');
            $table->string('status')->comment('-1 -not complied; 0 -Partially Complied; 1 -Complied');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complying_offices');
    }
};
