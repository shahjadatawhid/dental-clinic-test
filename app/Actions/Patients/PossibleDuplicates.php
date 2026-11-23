<?php

namespace App\Actions\Patients;

use App\Models\Patient;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds existing records that may already be the person being registered, so reception
 * can open the existing record instead of creating a second one. Never blocks: one phone
 * may belong to a whole family (children usually have no number of their own), and two
 * different people can share a name. Each patient always gets their own patient_code.
 *
 *  - same phone + same name → probably the same person (strong warning)
 *  - same phone only        → other family members on this number (shown for information)
 *  - same name + similar age, different phone → maybe the same person (warning)
 */
final class PossibleDuplicates
{
    public const SAME_PERSON = 'same_person';

    public const SAME_PHONE = 'same_phone';

    public const SAME_NAME = 'same_name';

    /** Words that are often written or left out inconsistently ("Md. Rakib" vs "Rakib"). */
    private const IGNORED_WORDS = [
        'md', 'mohammad', 'mohammed', 'muhammad', 'mohd', 'mst', 'mosammat', 'mosamat', 'most',
        'sk', 'mr', 'mrs', 'ms', 'miss', 'dr',
    ];

    /** Ages differing by at most this many years count as "similar". */
    private const AGE_TOLERANCE = 1;

    /**
     * Everything worth showing for the details typed so far, strongest match first.
     *
     * @param  array<string, mixed>  $input  full_name, phone, alt_phone, date_of_birth, age_years
     * @return Collection<int, array{patient: Patient, match: string}>
     */
    public static function check(array $input, ?int $ignoreId = null): Collection
    {
        $input = PatientRules::clean($input);
        $phones = array_values(array_filter([$input['phone'] ?? null, $input['alt_phone'] ?? null], fn ($p) => is_string($p) && preg_match(Phone::BD_MOBILE, $p)));
        $words = self::words($input['full_name'] ?? null);

        $byPhone = self::samePhone($phones, $ignoreId);
        $byName = self::byNameAndAge($input['full_name'] ?? null, $input['date_of_birth'] ?? null, isset($input['age_years']) ? (int) $input['age_years'] : null, $ignoreId);

        $phoneMatches = $byPhone->map(fn (Patient $p) => [
            'patient' => $p,
            'match' => $words !== [] && self::words($p->full_name) === $words ? self::SAME_PERSON : self::SAME_PHONE,
        ]);
        $nameMatches = $byName
            ->reject(fn (Patient $p) => $byPhone->contains('id', $p->id))
            ->map(fn (Patient $p) => ['patient' => $p, 'match' => self::SAME_NAME]);

        $order = [self::SAME_PERSON => 0, self::SAME_NAME => 1, self::SAME_PHONE => 2];

        return $phoneMatches->concat($nameMatches)
            ->sortBy(fn (array $m) => [$order[$m['match']], $m['patient']->id])
            ->values();
    }

    /**
     * Patients reachable on any of these numbers (their phone or second phone).
     * Merged records are left out; inactive ones are included so they can be reactivated.
     *
     * @param  list<string>  $phones
     * @return Collection<int, Patient>
     */
    public static function samePhone(array $phones, ?int $ignoreId = null, int $limit = 20): Collection
    {
        if ($phones === []) {
            return collect();
        }

        return Patient::query()
            ->whereNull('merged_into_id')
            ->where(fn ($q) => $q->whereIn('phone', $phones)->orWhereIn('alt_phone', $phones))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderByDesc('is_active')->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Patients with the same name (ignoring case, dots, word order and "Md."/"Mst." etc.)
     * whose age is similar. When either age is unknown, the name alone counts.
     * Merged records are left out; inactive ones are included.
     *
     * @return Collection<int, Patient>
     */
    public static function byNameAndAge(
        ?string $name,
        ?string $dateOfBirth = null,
        ?int $ageYears = null,
        ?int $ignoreId = null,
        int $limit = 5,
    ): Collection {
        $words = self::words($name);
        if ($words === []) {
            return collect();
        }

        $age = $dateOfBirth ? Carbon::parse($dateOfBirth)->age : $ageYears;
        $longest = collect($words)->sortByDesc(fn ($w) => strlen($w))->first();

        return Patient::query()
            ->whereNull('merged_into_id')
            ->where('full_name', 'like', '%'.addcslashes($longest, '%_\\').'%')
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderByDesc('is_active')->orderBy('id')
            ->limit(200)
            ->get()
            ->filter(fn (Patient $p) => self::words($p->full_name) === $words)
            ->filter(function (Patient $p) use ($age, $dateOfBirth) {
                if ($dateOfBirth && $p->date_of_birth?->toDateString() === Carbon::parse($dateOfBirth)->toDateString()) {
                    return true;
                }
                $theirAge = $p->age();

                return $age === null || $theirAge === null || abs($theirAge - $age) <= self::AGE_TOLERANCE;
            })
            ->take($limit)
            ->values();
    }

    /**
     * "Md. Rakib  HOSSAIN" → ['hossain', 'rakib'] (sorted, so word order doesn't matter).
     *
     * @return list<string>
     */
    public static function words(?string $name): array
    {
        $clean = Str::of((string) $name)->lower()->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ')->squish()->value();

        $words = array_values(array_filter(
            explode(' ', $clean),
            fn ($w) => $w !== '' && ! in_array($w, self::IGNORED_WORDS, true),
        ));
        sort($words);

        return $words;
    }
}
