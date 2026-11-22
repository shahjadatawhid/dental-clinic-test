<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A version of a doctor's weekly routine, valid from effective_from to effective_to
        // (null = until further notice). Changing hours creates a new version, so the
        // routine on any past date can always be looked up.
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('note')->nullable();
            $table->userstamps();
            $table->timestamps();

            $table->unique(['doctor_id', 'effective_from']);
            $table->index(['doctor_id', 'effective_to']);
        });

        // Working blocks inside a version, e.g. Saturday 10:00–13:00 in Chair A.
        Schema::create('doctor_schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday (Carbon dayOfWeek)
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('chair_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['doctor_schedule_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_blocks');
        Schema::dropIfExists('doctor_schedules');
    }
};
