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
use ReflectionProperty;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class Settings implements Arrayable, Castable, Jsonable, JsonSerializable
{
    /** @var array<class-string, ReflectionClass<static>> */
    protected static array $reflectionCache = [];

    /** @var array<class-string, array<string, ReflectionProperty>> */
    protected static array $propertyCache = [];

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
        $reflection = static::getReflection();
        $constructor = $reflection->getConstructor();

        if (! $constructor || $constructor->getNumberOfParameters() === 0) {
            return static::hydrateProperties($reflection->newInstance(), $data);
        }

        return $reflection->newInstanceArgs(array_map(
            fn (ReflectionParameter $param) => static::resolveValue(
                $param->getName(),
                $data,
                $param
            ),
            $constructor->getParameters()
        ));
    }

    /**
     * @param array<string, mixed>                                  $data
     * @throws ReflectionException
     */
    protected static function resolveValue(string $name, array $data, ReflectionParameter|ReflectionProperty $target): mixed
    {
        $value = match (true) {
            array_key_exists($name, $data) => $data[$name],
            $target instanceof ReflectionParameter && $target->isDefaultValueAvailable() => $target->getDefaultValue(),
            $target->getType()?->allowsNull() => null,
            default => throw new InvalidArgumentException("Missing required setting: {$name}"),
        };

        return static::castValue($value, $target);
    }

    protected static function castValue(mixed $value, ReflectionParameter|ReflectionProperty $target): mixed
    {
        if (static::isEnumCollection($target, $value)) {
            if (!is_array($value)) {
                throw new InvalidArgumentException(
                    sprintf('Setting "%s" expects an array for collection casting.', $target->getName())
                );
            }

            return static::castToEnumCollection($target, $value);
        }

        if (static::isSingleEnum($target)) {
            return static::castToSingleEnum($target, $value);
        }

        return $value;
    }

    protected static function isEnumCollection(ReflectionParameter|ReflectionProperty $target, mixed $value): bool
    {
        return is_array($value) && !empty($target->getAttributes(AsCollection::class));
    }

    protected static function isSingleEnum(ReflectionParameter|ReflectionProperty $target): bool
    {
        $type = $target->getType();
        return $type instanceof ReflectionNamedType && enum_exists($type->getName());
    }

    /**
     * @param array<mixed>               $value
     *
     * @return array<mixed>
     */
    protected static function castToEnumCollection(ReflectionParameter|ReflectionProperty $target, array $value): array
    {
        $attributes = $target->getAttributes(AsCollection::class);

        /** @var class-string<BackedEnum> $enumClass */
        $enumClass = $attributes[0]->newInstance()->type;

        return array_map(fn($item) => static::toEnum($enumClass, $item), $value);
    }

    protected static function castToSingleEnum(ReflectionParameter|ReflectionProperty $target, mixed $value): ?BackedEnum
    {
        $type = $target->getType();

        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

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

        $properties = static::getReflectedProperties();

        foreach ($data as $key => $value) {
            if (isset($properties[$key])) {
                $property = $properties[$key];
                $instance->{$key} = static::castValue($value, $property);
            }
        }

        return $instance;
    }

    /**
     * @return ReflectionClass<static>
     */
    protected static function getReflection(): ReflectionClass
    {
        return static::$reflectionCache[static::class] ??= new ReflectionClass(static::class);
    }

    /**
     * @return array<string, ReflectionProperty>
     */
    protected static function getReflectedProperties(): array
    {
        if (isset(static::$propertyCache[static::class])) {
            return static::$propertyCache[static::class];
        }

        $properties = [];
        foreach (static::getReflection()->getProperties() as $property) {
            $properties[$property->getName()] = $property;
        }

        return static::$propertyCache[static::class] = $properties;
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

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
