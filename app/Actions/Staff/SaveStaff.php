<?php

namespace App\Actions\Staff;

use App\Enums\StaffType;
use App\Models\Doctor;
use App\Models\Staff;
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a staff member and, for doctors, their doctor details.
 */
class SaveStaff
{
    /** Private disk: signatures are only served through an authorised route (M06). */
    public const SIGNATURE_DISK = 'local';

    public const SIGNATURE_DIR = 'signatures';

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(?Staff $staff, array $input, ?UploadedFile $signature = null): Staff
    {
        $input['phone'] = Phone::normalize($input['phone'] ?? null);
        $input['email'] = isset($input['email']) && trim((string) $input['email']) !== '' ? strtolower(trim((string) $input['email'])) : null;
        foreach (['user_id', 'designation', 'gender', 'joined_on', 'left_on', 'notes'] as $optional) {
            $input[$optional] = ($input[$optional] ?? '') === '' ? null : $input[$optional];
        }

        $doctor = $staff?->doctor;
        $isDoctor = ($input['type'] ?? null) === StaffType::Doctor->value;

        $data = Validator::make($input, [
            'full_name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(StaffType::class)],
            'designation' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'phone' => ['nullable', 'regex:'.Phone::BD_MOBILE],
            'email' => ['nullable', 'email', 'max:255'],
            'joined_on' => ['nullable', 'date'],
            'left_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('staff', 'user_id')->ignore($staff?->id)],

            'doctor' => ['array'],
            'doctor.registration_no' => ['nullable', 'string', 'max:50', Rule::unique('doctors', 'registration_no')->ignore($doctor?->id)],
            'doctor.specialty' => ['nullable', 'string', 'max:100'],
            'doctor.qualifications' => ['nullable', 'string', 'max:255'],
            'doctor.default_slot_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'doctor.calendar_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'doctor.is_bookable' => ['boolean'],
        ], [
            'phone.regex' => __('Enter an 11-digit Bangladeshi mobile number, e.g. 01711000000.'),
            'user_id.unique' => __('That login is already linked to another staff member.'),
        ], [
            'doctor.registration_no' => __('registration number'),
        ])->validate();

        if ($signature !== null) {
            Validator::make(['signature' => $signature], [
                'signature' => ['image', 'mimes:png,jpg,jpeg', 'max:1024'],
            ])->validate();
        }

        if ($doctor !== null && ! $isDoctor) {
            throw ValidationException::withMessages([
                'type' => __('This person has a doctor record (used by appointments and prescriptions), so they must stay a doctor. Deactivate them instead.'),
            ]);
        }

        return DB::transaction(function () use ($staff, $data, $isDoctor, $doctor, $signature) {
            $staff ??= new Staff;
            $staff->fill([
                'full_name' => trim($data['full_name']),
                'type' => $data['type'],
                'designation' => $data['designation'],
                'gender' => $data['gender'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'joined_on' => $data['joined_on'],
                'left_on' => $data['left_on'],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'notes' => $data['notes'],
                'user_id' => $data['user_id'],
            ])->save();

            if ($isDoctor) {
                $details = $data['doctor'] ?? [];
                $doctor ??= new Doctor(['staff_id' => $staff->id]);
                $doctor->fill([
                    'registration_no' => ($details['registration_no'] ?? '') !== '' ? trim($details['registration_no']) : null,
                    'specialty' => ($details['specialty'] ?? '') !== '' ? $details['specialty'] : null,
                    'qualifications' => ($details['qualifications'] ?? '') !== '' ? $details['qualifications'] : null,
                    'default_slot_minutes' => ($details['default_slot_minutes'] ?? '') !== '' ? (int) $details['default_slot_minutes'] : null,
                    'calendar_color' => $details['calendar_color'] ?? '#0ea5e9',
                    'is_bookable' => (bool) ($details['is_bookable'] ?? true),
                ]);

                if ($signature !== null) {
                    $old = $doctor->signature_path;
                    $doctor->signature_path = $signature->store(self::SIGNATURE_DIR, self::SIGNATURE_DISK);
                    if ($old !== null) {
                        DB::afterCommit(fn () => Storage::disk(self::SIGNATURE_DISK)->delete($old));
                    }
                }

                $doctor->save();
            }

            return $staff->load('doctor');
        });
    }
}
