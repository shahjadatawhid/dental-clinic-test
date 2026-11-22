<?php

namespace App\Actions\Patients;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;

/**
 * One search box for patients: name, phone, patient code or old (legacy) code.
 *
 *  - "nusrat jah"       → every word must appear in the name (any order)
 *  - "0171", "+880 1711-000000", "1711000000" → phone or second phone contains the digits
 *  - "FD-11-26-000012", "fd-01-23-0208", "000012" → patient code or legacy code
 *
 * Exact code matches come first, then active patients, then by name. One phone can
 * belong to a whole family, so a phone search lists every patient on that number.
 */
final class SearchPatients
{
    /** Below this many digits a number is too short to search phones by. */
    private const MIN_PHONE_DIGITS = 3;

    /**
     * @param  bool  $byPhone  false for users who may only identify patients by name/code
     * @param  string  $status  active | inactive | all (all also includes merged records)
     * @return Builder<Patient>
     */
    public static function query(string $term, bool $byPhone = true, string $status = 'active'): Builder
    {
        $term = trim(preg_replace('/\s+/', ' ', $term) ?? '');
        $code = strtoupper(str_replace(' ', '', $term));

        $query = Patient::query()
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false)->whereNull('merged_into_id'));

        if ($term === '') {
            return $query->orderByDesc('registered_on')->orderByDesc('id');
        }

        $words = self::nameWords($term);
        $digits = self::phoneDigits($term);
        $looksLikeCode = preg_match('/\d/', $term) === 1 && preg_match('/^[A-Z0-9-]+$/', $code) === 1;

        if ($words === [] && ($digits === null || ! $byPhone) && ! $looksLikeCode) {
            return $query->whereRaw('1 = 0'); // nothing searchable typed, e.g. "%" or "01"
        }

        $query->where(function (Builder $q) use ($words, $digits, $looksLikeCode, $code, $byPhone) {
            if ($words !== []) {
                $q->orWhere(function (Builder $name) use ($words) {
                    foreach ($words as $word) {
                        $name->where('full_name', 'like', '%'.self::escape($word).'%');
                    }
                });
            }
            if ($byPhone && $digits !== null) {
                $like = '%'.$digits.'%';
                $q->orWhere('phone', 'like', $like)->orWhere('alt_phone', 'like', $like);
            }
            if ($looksLikeCode) {
                $like = '%'.self::escape($code).'%';
                $q->orWhere('patient_code', 'like', $like)->orWhere('legacy_code', 'like', $like);
            }
        });

        return $query
            ->orderByRaw("(patient_code = ? or coalesce(legacy_code, '') = ?) desc", [$code, $code])
            ->orderByDesc('is_active')
            ->orderBy('full_name')
            ->orderBy('id');
    }

    /** @return list<string> words to look for in the name (letters only; "Md." etc. kept as typed) */
    private static function nameWords(string $term): array
    {
        if (preg_match('/\p{L}/u', $term) !== 1) {
            return [];
        }

        return array_values(array_filter(
            explode(' ', trim(preg_replace('/[^\p{L}\p{N}\s.]+/u', ' ', $term) ?? '')),
            fn ($w) => $w !== '' && $w !== '.',
        ));
    }

    /** "+880 1711-000000" → "01711000000", "1711 0" → "17110"; null if it isn't a number */
    private static function phoneDigits(string $term): ?string
    {
        if (preg_match('/^[\d\s()+-]+$/', $term) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $term) ?? '';
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);   // 8801711… → 01711…
        }

        return strlen($digits) >= self::MIN_PHONE_DIGITS ? $digits : null;
    }

    private static function escape(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
