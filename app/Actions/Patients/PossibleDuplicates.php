<?php

namespace App\Actions\Patients;

use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds records that may already be the person being registered.
 *
 *  - Same phone  → blocked (clinic rule: each patient has their own number), see phoneOwner().
 *  - Same name + similar age → only a warning; two different people can share a name.
 */
final class PossibleDuplicates
{
    /** Words that are often written or left out inconsistently ("Md. Rakib" vs "Rakib"). */
    private const IGNORED_WORDS = [
        'md', 'mohammad', 'mohammed', 'muhammad', 'mohd', 'mst', 'mosammat', 'mosamat', 'most',
        'sk', 'mr', 'mrs', 'ms', 'miss', 'dr',
    ];

    /** Ages differing by at most this many years count as "similar". */
    private const AGE_TOLERANCE = 1;

    /** The patient who already has this phone (inactive and merged records included). */
    public static function phoneOwner(?string $phone, ?int $ignoreId = null): ?Patient
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        return Patient::query()
            ->where('phone', $phone)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();
    }

    /**
     * Patients with the same name (ignoring case, dots, word order and "Md."/"Mst." etc.)
     * whose age is similar. When either age is unknown, the name alone counts.
     * Merged records are left out; inactive ones are included so they can be reactivated.
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
