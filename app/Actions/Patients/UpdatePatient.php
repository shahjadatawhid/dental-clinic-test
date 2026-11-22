<?php

namespace App\Actions\Patients;

use App\Models\Patient;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Edits a patient's demographics. The patient code, registration date and merge link
 * never change here; every change is kept in the audit history (Auditable).
 */
class UpdatePatient
{
    /** @param  array<string, mixed>  $input  fields from PatientRules, plus is_active */
    public function handle(Patient $patient, array $input): Patient
    {
        if ($patient->merged_into_id !== null) {
            throw ValidationException::withMessages(['full_name' => __('This record was merged into another patient and can no longer be edited.')]);
        }

        $input = PatientRules::clean($input);

        $data = Validator::make(
            $input,
            [...PatientRules::rules(), 'is_active' => ['sometimes', 'boolean']],
            PatientRules::messages(),
        )->validate();

        // Date of birth wins. A newly typed age is remembered with today's date;
        // an unchanged age keeps the day it was first given, so it keeps growing.
        if (! array_key_exists('date_of_birth', $data) && ! array_key_exists('age_years', $data)) {
            // Age not part of this edit.
        } elseif (! empty($data['date_of_birth'])) {
            $data['age_years'] = null;
            $data['age_recorded_on'] = null;
        } elseif (! isset($data['age_years'])) {
            $data['age_recorded_on'] = null;
        } elseif ($patient->date_of_birth !== null || (int) $data['age_years'] !== $patient->age()) {
            $data['age_recorded_on'] = today();
        } else {
            // Unchanged: the form shows today's age, store the original pair untouched.
            unset($data['age_years']);
        }

        $patient->fill($data);
        $patient->save();

        return $patient;
    }
}
