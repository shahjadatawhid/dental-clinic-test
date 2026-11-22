<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Children are usually brought (and registered on the phone of) a parent or guardian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('guardian_name')->nullable()->after('emergency_contact_phone');
            $table->string('guardian_relation', 20)->nullable()->after('guardian_name'); // mother | father | grandparent | sibling | spouse | other
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['guardian_name', 'guardian_relation']);
        });
    }
};
