<?php

namespace Androlax2\LaravelModelTypedSettings\Commands;

use Androlax2\LaravelModelTypedSettings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class SettingsCacheCommand extends Command
{
    protected $signature = 'settings:cache';

    protected $description = 'Cache metadata for typed settings.';

    /**
     * @throws ReflectionException
     */
    public function handle(): void
    {
        $this->info('Caching model-typed settings metadata...');

        $settingsClasses = $this->discoverSettingsClasses();
        /** @var array<class-string<Settings>, array{defaults: array<string, mixed>}> $cache */
        $cache = [];

        foreach ($settingsClasses as $class) {
            $reflection = new ReflectionClass($class);
            $defaultInstance = $class::fromArray([]);

            $properties = array_map(
                fn($prop) => $prop->getName(),
                $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED)
            );

            $properties = array_values(array_filter($properties, fn (string $name) => !in_array($name, ['reflectionCache', 'propertyCache', 'optimizedMetadata'])));

            $cache[$class] = [
                'defaults' => $defaultInstance->toArray(stripDefaults: false),
                'properties' => $properties,
            ];
        }

        $path = $this->laravel->bootstrapPath('cache/typed-settings.php');

        $content = '<?php return ' . var_export($cache, true) . ';';
        File::put($path, $content);

        $this->info("Successfully cached " . count($cache) . " classes to bootstrap/cache.");
    }

    /**
     * @return array<int, class-string<Settings>>
     */
    protected function discoverSettingsClasses(): array
    {
        $appPath = app_path();
        if (!File::isDirectory($appPath)) return [];

        $files = File::allFiles($appPath);
        $discovered = [];

        foreach ($files as $file) {
            $path = $file->getRelativePathname();
            $className = 'App\\' . str_replace(['/', '.php'], ['\\', ''], $path);

            if (class_exists($className) && is_subclass_of($className, Settings::class)) {
                $reflection = new ReflectionClass($className);
                if (!$reflection->isAbstract()) {
                    /** @var class-string<Settings> $className */
                    $discovered[] = $className;
                }
            }
        }

        return $discovered;
    }
}
