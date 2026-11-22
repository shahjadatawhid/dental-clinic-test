<?php

namespace App\Actions\Patients;

use App\Support\Phone;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Validation and clean-up shared by RegisterPatient and UpdatePatient.
 */
final class PatientRules
{
    /**
     * @param  int|null  $ignoreId  the patient being edited (their own phone is fine)
     * @return array<string, list<mixed>>
     */
    public static function rules(?int $ignoreId = null): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:120'],
            'phone' => ['bail', 'nullable', 'regex:'.Phone::BD_MOBILE, self::phoneNotTaken($ignoreId)],
            'alt_phone' => ['nullable', 'regex:'.Phone::BD_MOBILE, 'different:phone'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'preferred_language' => ['nullable', Rule::in(['bn', 'en'])],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'regex:'.Phone::BD_MOBILE],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'legacy_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Clinic rule: every patient has their own mobile number (family members can't share
     * one). The message names the owner so reception can open the existing record.
     */
    public static function phoneNotTaken(?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
            $owner = PossibleDuplicates::phoneOwner((string) $value, $ignoreId);

            if ($owner) {
                $fail(__('This phone is already registered to :code (:name). Each patient needs their own number.', [
                    'code' => $owner->patient_code,
                    'name' => $owner->full_name,
                ]));
            }
        };
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        $phone = __('Enter an 11-digit Bangladeshi mobile number, e.g. 01711000000.');

        return [
            'phone.regex' => $phone,
            'alt_phone.regex' => $phone,
            'emergency_contact_phone.regex' => $phone,
            'alt_phone.different' => __('The second phone is the same as the first.'),
            'date_of_birth.before_or_equal' => __('Date of birth cannot be in the future.'),
        ];
    }

    /**
     * Trim text, turn blanks into null, normalise phones and the legacy code.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(array $input): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $input);

        foreach (['phone', 'alt_phone', 'emergency_contact_phone'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = Phone::normalize($input[$field]);
            }
        }

        if (isset($input['full_name'])) {
            $input['full_name'] = preg_replace('/\s+/', ' ', $input['full_name']);
        }
        if (isset($input['email'])) {
            $input['email'] = strtolower($input['email']);
        }
        if (isset($input['legacy_code'])) {
            $input['legacy_code'] = strtoupper($input['legacy_code']);
        }

        return $input;
    }
}
