<?php

namespace App\Actions\Patients;

use App\Enums\SequenceSeries;
use App\Models\Patient;
use App\Services\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Registers a new patient and gives them the next patient code (FD-MM-YY-NNNNNN).
 *
 * Duplicate warnings (same phone / similar name) are shown by the screen before calling
 * this (M03-03); this action itself never blocks a registration as a duplicate.
 */
class RegisterPatient
{
    public function __construct(private NumberSequenceService $numbers) {}

    /**
     * @param  array<string, mixed>  $input  fields from PatientRules, plus optional
     *                                       registered_on (back-dating, e.g. imports)
     */
    public function handle(array $input): Patient
    {
        $input = PatientRules::clean($input);

        $data = Validator::make(
            $input,
            [...PatientRules::rules(), 'registered_on' => ['nullable', 'date', 'before_or_equal:today']],
            PatientRules::messages(),
        )->validate();

        $registeredOn = isset($data['registered_on']) ? Carbon::parse($data['registered_on']) : today();

        // Date of birth wins; otherwise remember the age and the day it was given.
        if (! empty($data['date_of_birth'])) {
            $data['age_years'] = null;
        }
        $ageRecordedOn = empty($data['date_of_birth']) && isset($data['age_years']) ? $registeredOn : null;

        return DB::transaction(function () use ($data, $registeredOn, $ageRecordedOn) {
            $patient = new Patient;
            $patient->fill(collect($data)->except('registered_on')->all());
            $patient->preferred_language = $data['preferred_language'] ?? 'bn';
            $patient->age_recorded_on = $ageRecordedOn;
            $patient->registered_on = $registeredOn;
            // Taken inside this transaction: if saving fails, the code is released.
            $patient->patient_code = $this->numbers->next(SequenceSeries::Patient, $registeredOn);
            $patient->save();

            return $patient;
        });
    }
}
