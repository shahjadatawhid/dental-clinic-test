<?php

namespace App\Actions\Patients;

use App\Enums\SequenceSeries;
use App\Models\Patient;
use App\Services\NumberSequenceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Registers a new patient and gives them the next patient code (FD-MM-YY-NNNNNN).
 *
 * A phone that already belongs to another patient is refused (clinic rule: one number
 * per patient). A similar name + age is only a warning, shown by the screen before
 * calling this (PossibleDuplicates::byNameAndAge); this action does not block on it.
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

        try {
            return $this->save($data, $registeredOn, $ageRecordedOn);
        } catch (UniqueConstraintViolationException $e) {
            // Someone saved the same phone a moment ago, after our check passed.
            if (! str_contains($e->getMessage(), 'patients_phone_unique')) {
                throw $e;
            }
            $owner = PossibleDuplicates::phoneOwner($data['phone'] ?? null);

            throw ValidationException::withMessages(['phone' => __(
                'This phone is already registered to :code (:name). Each patient needs their own number.',
                ['code' => $owner->patient_code ?? '?', 'name' => $owner->full_name ?? '?'],
            )]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function save(array $data, Carbon $registeredOn, ?Carbon $ageRecordedOn): Patient
    {
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
