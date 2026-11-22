<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads and writes clinic settings. Registered as a singleton; use the setting() helper.
 */
class Settings
{
    private const CACHE_KEY = 'app.settings.v1';

    /** @var array<string, mixed>|null */
    private ?array $stored = null;

    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $stored = $this->stored();

        return array_key_exists($key, $stored)
            ? $definition->type->cast($stored[$key])
            : $definition->default;
    }

    /** @return array<string, mixed> every setting with its current value */
    public function all(): array
    {
        return collect(Definitions::all())->mapWithKeys(fn ($d, $key) => [$key => $this->get($key)])->all();
    }

    /**
     * Save several values. Values equal to the default are removed from the table
     * (so a later change of default applies); others are upserted.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                $definition = $this->definition($key);
                $value = $definition->type->cast($value);
                $value = $value === '' ? null : $value;

                $row = Setting::where('key', $key)->first();

                if ($value === $definition->default) {
                    $row?->delete();

                    continue;
                }

                if ($row === null) {
                    Setting::create(['key' => $key, 'value' => $value]);
                } elseif ($row->value !== $value) {
                    $row->update(['value' => $value]);
                }
            }
        });

        $this->flush();
    }

    public function flush(): void
    {
        $this->stored = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        return $this->stored ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::pluck('value', 'key')->all(),
        );
    }

    private function definition(string $key): SettingDefinition
    {
        return Definitions::find($key)
            ?? throw new InvalidArgumentException("Unknown setting [{$key}]. Add it to App\\Settings\\Definitions.");
    }
}
