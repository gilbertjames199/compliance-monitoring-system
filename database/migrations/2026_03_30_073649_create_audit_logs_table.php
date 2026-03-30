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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
              // WHAT happened
            $table->string('event'); // submitted, updated, validated, returned

            // WHO did it — store recid values, NO FK constraints (cross-db)
            $table->unsignedInteger('user_id')->nullable();   // owner of the complying office
            $table->string('acted_by')->nullable();  // auth()->user()->recid

            $table->timestamp('action_at')->useCurrent();

            // RELATIONS — only constrain what's in the same DB
            $table->unsignedBigInteger('requirement_id')->nullable();   // just store the ID, no FK
            $table->string('requirement_name')->nullable();

            $table->unsignedBigInteger('complying_office_id')->nullable(); // just store the ID, no FK
            $table->string('office_name')->nullable();

            // Store agency name as string (no FK — it's on mysql2)
            $table->string('requiring_agency_name')->nullable();

            // STATE BEFORE
            $table->string('old_status')->nullable();
            $table->string('old_validation_status')->nullable();

            // STATE AFTER
            $table->string('new_status')->nullable();
            $table->string('new_validation_status')->nullable();

            // EXTRA INFO
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
