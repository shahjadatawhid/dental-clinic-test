<?php

namespace App\Actions\Settings;

use App\Settings\Definitions;
use App\Settings\Settings;
use Illuminate\Support\Facades\Validator;

class UpdateSettings
{
    public function __construct(private Settings $settings) {}

    /**
     * Validate and save settings. Form fields use "__" in place of "." (clinic__name for
     * clinic.name) because Livewire and the validator treat dots as nesting.
     *
     * @param  array<string, mixed>  $input  form key => value
     */
    public function handle(array $input): void
    {
        $rules = [];
        $attributes = [];
        foreach (Definitions::all() as $key => $definition) {
            $field = self::field($key);
            if (array_key_exists($field, $input)) {
                $rules[$field] = $definition->validationRules();
                $attributes[$field] = __($definition->label);
            }
        }

        $valid = Validator::make($input, $rules, [], $attributes)->validate();

        $values = [];
        foreach ($valid as $field => $value) {
            $values[self::key($field)] = $value;
        }

        $this->settings->setMany($values);
    }

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public static function key(string $field): string
    {
        return str_replace('__', '.', $field);
    }
}
