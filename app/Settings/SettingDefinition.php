<?php

namespace App\Settings;

final readonly class SettingDefinition
{
    /**
     * @param  list<mixed>  $rules  Laravel validation rules (besides the type rule)
     */
    public function __construct(
        public string $key,
        public string $group,
        public SettingType $type,
        public mixed $default,
        public string $label,
        public ?string $help = null,
        public array $rules = [],
        public ?string $suffix = null,
    ) {}

    /** @return list<mixed> */
    public function validationRules(): array
    {
        $typeRule = match ($this->type) {
            SettingType::Boolean => ['boolean'],
            SettingType::Integer => ['integer'],
            SettingType::Decimal => ['numeric'],
            SettingType::Text => ['string', 'max:255'],
            SettingType::LongText => ['string', 'max:2000'],
        };

        $presence = $this->type === SettingType::Boolean ? ['required'] : ['nullable'];

        return [...$presence, ...$typeRule, ...$this->rules];
    }
}
