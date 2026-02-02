<?php

namespace Androlax2\LaravelModelTypedSettings;

use Androlax2\LaravelModelTypedSettings\Attributes\AsCollection;
use Androlax2\LaravelModelTypedSettings\Casts\GenericSettingsBridge;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

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
            $value = null;

            if (array_key_exists($name, $data)) {
                $value = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $value = $param->getDefaultValue();
            } else {
                if (! $param->allowsNull()) {
                    throw new InvalidArgumentException("Missing required setting: {$name}");
                }
            }

            $type = $param->getType();
            $collectionAttr = $param->getAttributes(AsCollection::class);

            if (!empty($collectionAttr) && is_array($value)) {
                /** @var class-string<BackedEnum> $castTo */
                $castTo = $collectionAttr[0]->newInstance()->type;
                if (enum_exists($castTo)) {
                    $value = array_map(function ($item) use ($castTo) {
                        if ($item instanceof $castTo) return $item;
                        if (!is_string($item) && !is_int($item)) {
                            throw new InvalidArgumentException("Enum value must be string or int.");
                        }
                        return $castTo::from($item);
                    }, $value);
                }
            } elseif ($type instanceof ReflectionNamedType && enum_exists($type->getName())) {
                /** @var class-string<BackedEnum> $enumClass */
                $enumClass = $type->getName();
                if (is_string($value) || is_int($value)) {
                    $value = $enumClass::from($value);
                }
            }

            $args[] = $value;
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);

        return array_map(function (mixed $value) {
            return is_array($value)
                ? array_map(fn (mixed $item) => $item instanceof BackedEnum ? $item->value : $item,
                $value)
                : ($value instanceof BackedEnum ? $value->value : $value);
        }, $data);
    }

    /**
     * @param int $options
     */
    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
