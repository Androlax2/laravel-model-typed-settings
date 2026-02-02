<?php

namespace Androlax2\LaravelModelTypedSettings;

use Androlax2\LaravelModelTypedSettings\Casts\GenericSettingsBridge;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class Settings implements Arrayable, Castable, Jsonable, JsonSerializable
{
    /**
     * @param array<string, mixed> $arguments
     */
    public static function castUsing(array $arguments): GenericSettingsBridge
    {
        return new GenericSettingsBridge(static::class);
    }

    /**
     * @param array<string, mixed> $data
     * @throws ReflectionException
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if (! $constructor || $constructor->getNumberOfParameters() === 0) {
            $instance = $reflection->newInstance();
            foreach ($data as $key => $value) {
                if (property_exists($instance, $key)) {
                    $instance->{$key} = $value;
                }
            }

            return $instance;
        }

        $params = $constructor->getParameters();
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $data)) {
                $args[] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                if ($param->allowsNull()) {
                    $args[] = null;
                } else {
                    throw new InvalidArgumentException("Missing required setting: {$name}");
                }
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param int $options
     */
    public function toJson($options = 0): false|string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
