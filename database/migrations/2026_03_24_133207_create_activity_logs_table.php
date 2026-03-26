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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // user
            $table->foreignId('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_role')->nullable();

            // action info
            $table->string('action');
            $table->string('module');
            $table->unsignedBigInteger('record_id')->nullable();

            // references
            $table->unsignedBigInteger('required_document_id')->nullable();
            $table->unsignedBigInteger('complying_office_id')->nullable();
            $table->string('department_code')->nullable();

            // details
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // status snapshot
            $table->string('status')->nullable();
            $table->string('validation_status')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
