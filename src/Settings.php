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
use ReflectionParameter;
use ReflectionNamedType;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class Settings implements Arrayable, Castable, Jsonable, JsonSerializable
{
    public static function castUsing(array $arguments): GenericSettingsBridge
    {
        return new GenericSettingsBridge(static::class);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ReflectionException
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if (! $constructor || $constructor->getNumberOfParameters() === 0) {
            return static::hydrateProperties($reflection->newInstance(), $data);
        }

        return $reflection->newInstanceArgs(array_map(
            fn (ReflectionParameter $param) => static::resolveParameter($param, $data),
            $constructor->getParameters()
        ));
    }

    /**
     * @param array<string, mixed>               $data
     *
     * @throws ReflectionException
     */
    protected static function resolveParameter(ReflectionParameter $param, array $data): mixed
    {
        $name = $param->getName();

        $value = match (true) {
            array_key_exists($name, $data) => $data[$name],
            $param->isDefaultValueAvailable() => $param->getDefaultValue(),
            $param->allowsNull() => null,
            default => throw new InvalidArgumentException("Missing required setting: {$name}"),
        };

        return static::castValue($value, $param);
    }

    protected static function castValue(mixed $value, ReflectionParameter $param): mixed
    {
        if (static::isEnumCollection($param, $value)) {
            return static::castToEnumCollection($param, $value);
        }

        if (static::isSingleEnum($param)) {
            return static::castToSingleEnum($param, $value);
        }

        return $value;
    }

    protected static function isEnumCollection(ReflectionParameter $param, mixed $value): bool
    {
        return is_array($value) && !empty($param->getAttributes(AsCollection::class));
    }

    protected static function isSingleEnum(ReflectionParameter $param): bool
    {
        $type = $param->getType();

        return $type instanceof ReflectionNamedType && enum_exists($type->getName());
    }

    /**
     * @param array<string, mixed>               $value
     *
     * @return array<string, mixed>
     */
    protected static function castToEnumCollection(ReflectionParameter $param, array $value): array
    {
        $attributes = $param->getAttributes(AsCollection::class);

        /** @var class-string<BackedEnum> $enumClass */
        $enumClass = $attributes[0]->newInstance()->type;

        return array_map(fn($item) => static::toEnum($enumClass, $item), $value);
    }

    protected static function castToSingleEnum(ReflectionParameter $param, mixed $value): ?BackedEnum
    {
        /** @var ReflectionNamedType $type */
        $type = $param->getType();

        /** @var class-string<BackedEnum> $enumClass */
        $enumClass = $type->getName();

        return static::toEnum($enumClass, $value);
    }

    /**
     * @param class-string<BackedEnum> $enumClass
     */
    protected static function toEnum(string $enumClass, mixed $value): ?BackedEnum
    {
        if ($value instanceof $enumClass || $value === null) {
            return $value;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException("Enum value for {$enumClass} must be string or int.");
        }

        return $enumClass::from($value);
    }

    /**
     * @param array<string, mixed>  $data
     */
    protected static function hydrateProperties(object $instance, array $data): static
    {
        if (!$instance instanceof static) {
            throw new InvalidArgumentException(
                sprintf('Expected instance of %s, got %s', static::class, get_class($instance))
            );
        }

        foreach ($data as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }

    public function toArray(): array
    {
        return array_map(function (mixed $value) {
            if ($value instanceof BackedEnum) {
                return $value->value;
            }

            return is_array($value)
                ? array_map(fn ($item) => $item instanceof BackedEnum ? $item->value : $item, $value)
                : $value;
        }, get_object_vars($this));
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
