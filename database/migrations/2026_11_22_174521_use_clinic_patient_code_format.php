<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Q1 decided (2026-11-22): new patient codes use the clinic's own style FD-MM-YY-NNNNNN.
 * Databases created earlier got the placeholder FD-NNNNNN format. Switch them over — but
 * only if no patient code has been handed out yet, so issued codes are never reinterpreted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sequence = DB::table('number_sequences')->where('series', 'patient')->first();

        if ($sequence === null || $sequence->format !== '{prefix}-{number}') {
            return;
        }

        $used = DB::table('number_sequence_counters')
            ->where('number_sequence_id', $sequence->id)
            ->where('last_value', '>', 0)
            ->exists();

        if (! $used) {
            DB::table('number_sequences')->where('id', $sequence->id)->update([
                'format' => '{prefix}-{mm}-{yy}-{number}',
                'padding' => 6,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally empty: never change the format of a series that may be in use.
    }
};
