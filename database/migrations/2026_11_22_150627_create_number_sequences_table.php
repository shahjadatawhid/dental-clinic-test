<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How each series is formatted (RV-2026-000001, FD-000001, ...).
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series', 50)->unique();
            $table->string('prefix', 20);
            $table->string('format', 60);          // tokens: {prefix} {year} {yy} {number}
            $table->unsignedTinyInteger('padding'); // digits in {number}, zero-filled
            $table->boolean('reset_yearly')->default(false);
            $table->userstamps();
            $table->timestamps();
        });

        // The counter itself. period = year when reset_yearly, otherwise 0.
        // Locked with SELECT ... FOR UPDATE while a number is taken.
        Schema::create('number_sequence_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('number_sequence_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('period')->default(0);
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['number_sequence_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequence_counters');
        Schema::dropIfExists('number_sequences');
    }
};
