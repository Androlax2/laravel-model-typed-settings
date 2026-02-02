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

    /** @var array<class-string, array{defaults: array<string, mixed>}> */
    protected static array $optimizedMetadata = [];

    public static function castUsing(array $arguments): GenericSettingsBridge
    {
        return new GenericSettingsBridge(static::class);
    }

    /**
     * @param array<class-string, array{defaults: array<string, mixed>}> $cache
     */
    public static function setMetadataCache(array $cache): void
    {
        static::$optimizedMetadata = $cache;
    }

    /**
     * @return array{defaults: array<string, mixed>}|array<empty>
     */
    public static function getMetadataFor(string $class): array
    {
        return static::$optimizedMetadata[$class] ?? [];
    }

    public static function bootFromCache(): void
    {
        $path = function_exists('app')
            ? app()->bootstrapPath('cache/typed-settings.php')
            : base_path('bootstrap/cache/typed-settings.php');

        if (file_exists($path)) {
            /** @var array<class-string, array{defaults: array<string, mixed>}> $data */
            $data = require $path;
            static::$optimizedMetadata = $data;
        }
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

    /**
     * @throws ReflectionException
     */
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

        $settingsClass = static::getSettingsClass($target);

        if ($settingsClass) {
            if (is_null($value)) {
                return $target->getType()?->allowsNull() ? null : new $settingsClass();
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                return $settingsClass::fromArray($value);
            }

            return $value;
        }

        return static::coerceValue($value, $target);
    }

    /**
     * @return class-string<Settings>|null
     */
    protected static function getSettingsClass(ReflectionParameter|ReflectionProperty $target): ?string
    {
        $type = $target->getType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $name = $type->getName();
            if (is_subclass_of($name, Settings::class)) {
                return $name;
            }
        }

        return null;
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

    /**
     * @throws ReflectionException
     */
    public function toArray(bool $stripDefaults = true): array
    {
        $allVars = get_object_vars($this);

        if ($stripDefaults) {
            $defaultVars = static::$optimizedMetadata[static::class]['defaults'] ?? null;

            if ($defaultVars === null) {
                $reflection = static::getReflection();
                $defaultInstance = $reflection->newInstanceWithoutConstructor();
                $defaultVars = get_object_vars($defaultInstance);

                if (empty($defaultVars)) {
                    $defaultInstance = static::fromArray([]);
                    $defaultVars = get_object_vars($defaultInstance);
                }
            }

            $allVars = array_filter($allVars, function ($value, $key) use ($defaultVars) {
                if (!array_key_exists($key, $defaultVars)) {
                    return true;
                }

                $defaultValue = $defaultVars[$key];

                if ($value instanceof self) {
                    $result = $value->toArray();
                    return !empty($result);
                }

                if ($value instanceof BackedEnum) {
                    $compareValue = $defaultValue instanceof BackedEnum ? $defaultValue->value : $defaultValue;
                    return $value->value !== $compareValue;
                }

                return $value !== $defaultValue;
            }, ARRAY_FILTER_USE_BOTH);
        }

        return array_map(function (mixed $value) use ($stripDefaults) {
            if ($value instanceof BackedEnum) {
                return $value->value;
            }

            if ($value instanceof self) {
                return $value->toArray($stripDefaults);
            }

            if (is_array($value)) {
                return array_map(function ($item) use ($stripDefaults) {
                    if ($item instanceof BackedEnum) return $item->value;
                    if ($item instanceof self) return $item->toArray($stripDefaults);
                    return $item;
                }, $value);
            }

            return $value;
        }, $allVars);
    }

    protected static function coerceValue(mixed $value, ReflectionParameter|ReflectionProperty $target): mixed
    {
        $type = $target->getType();

        if ($type instanceof ReflectionNamedType && is_string($value)) {
            return match ($type->getName()) {
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => $value,
            };
        }

        return $value;
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    /**
     * @return array<mixed>
     * @throws ReflectionException
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
