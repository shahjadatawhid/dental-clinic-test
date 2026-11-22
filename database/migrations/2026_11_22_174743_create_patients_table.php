<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Patient demographics. Clinical details (alerts, history) live in separate tables (M03-08).
        // Never deleted: deactivated, or merged into another record (merged_into_id).
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_code', 30)->unique();        // FD-11-26-000001 (NumberSequenceService)
            $table->string('legacy_code', 30)->nullable()->index(); // old spreadsheet ID, e.g. FD-01-23-0208 (not unique: sheets may contain duplicates)
            $table->date('registered_on');

            $table->string('full_name')->index();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedTinyInteger('age_years')->nullable();  // when DOB is unknown…
            $table->date('age_recorded_on')->nullable();           // …and the day that age was given

            $table->string('phone', 20)->nullable()->index();
            $table->string('alt_phone', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('preferred_language', 5)->default('bn'); // bn | en
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('referred_by')->nullable();
            $table->text('notes')->nullable();                     // front-desk notes, not clinical

            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('merged_into_id')->nullable()->constrained('patients')->restrictOnDelete();
            $table->userstamps();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
