<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Doctor-only details. Name, phone, active status live on the staff row.
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->unique()->constrained('staff')->restrictOnDelete();
            $table->string('registration_no', 50)->nullable()->unique(); // BMDC registration
            $table->string('specialty', 100)->nullable();
            $table->string('qualifications')->nullable();               // e.g. "BDS, FCPS (Orthodontics)"
            $table->string('signature_path')->nullable();               // used on printed prescriptions (M06)
            $table->unsignedSmallInteger('default_slot_minutes')->nullable(); // null → setting appointments.default_slot_minutes
            $table->string('calendar_color', 7)->default('#0ea5e9');
            $table->boolean('is_bookable')->default(true);              // shown in appointment booking
            $table->userstamps();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
