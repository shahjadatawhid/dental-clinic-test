<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everyone who works at the clinic. Doctors also get a row in `doctors`.
        // Never deleted — set is_active = false (their name stays on past records).
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->string('type', 20)->index();            // App\Enums\StaffType
            $table->string('designation', 100)->nullable(); // e.g. "Dental surgeon", "Assistant"
            $table->string('gender', 10)->nullable();
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->userstamps();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
