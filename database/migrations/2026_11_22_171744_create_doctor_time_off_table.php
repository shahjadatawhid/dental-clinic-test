<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leave (whole days) and blocked time (part of a day) for a doctor.
        // ends_at is exclusive: a full day on 1 Dec is 2026-12-01 00:00 → 2026-12-02 00:00.
        Schema::create('doctor_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);  // App\Enums\TimeOffType
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->index(['doctor_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_time_off');
    }
};
