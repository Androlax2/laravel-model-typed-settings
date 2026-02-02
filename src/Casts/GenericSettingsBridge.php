<?php

namespace Androlax2\LaravelModelTypedSettings\Casts;

use Androlax2\LaravelModelTypedSettings\Settings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

/**
 * @implements CastsAttributes<Settings, Settings|array<string, mixed>>
 */
class GenericSettingsBridge implements CastsAttributes
{
    /**
     * @param class-string<Settings> $settingsClass
     */
    public function __construct(protected string $settingsClass) {}

    /**
     * @param  string|null  $value
     * @throws JsonException
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Settings
    {
        $json = is_string($value) ? $value : '';

        $data = $json !== ''
            ? json_decode($json, true, 512, JSON_THROW_ON_ERROR)
            : [];

        if (! is_array($data)) {
            $data = [];
        }

        return $this->settingsClass::fromArray($data);
    }

    /**
     * @param Settings|array<string, mixed>|null $value
     * @param array<string, mixed>               $attributes
     *
     * @throws JsonException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            $value = $this->settingsClass::fromArray($value);
        }

        if (! $value instanceof $this->settingsClass) {
            throw new InvalidArgumentException("The given value is not an instance of {$this->settingsClass}");
        }

        return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }
}
