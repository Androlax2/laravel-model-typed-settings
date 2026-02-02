<?php

use Androlax2\LaravelModelTypedSettings\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $path = base_path('bootstrap/cache/typed-settings.php');
    if (File::exists($path)) {
        File::delete($path);
    }
});

describe('Artisan Optimization Command', function () {
    test('the settings:cache command generates a cache file with defaults and property maps', function () {
        $fakeClassPath = app_path('Settings/FakeSettings.php');
        File::ensureDirectoryExists(dirname($fakeClassPath));

        File::put($fakeClassPath, <<<PHP
<?php
namespace App\Settings;
use Androlax2\LaravelModelTypedSettings\Settings;

class FakeSettings extends Settings {
    public string \$theme = 'dark';
    public bool \$notifications = true;
}
PHP
        );

        require_once $fakeClassPath;

        Artisan::call('settings:cache');

        $path = base_path('bootstrap/cache/typed-settings.php');
        expect(File::exists($path))->toBeTrue();

        $cachedData = require $path;

        expect($cachedData)->toHaveKey('App\Settings\FakeSettings')
                           ->and($cachedData['App\Settings\FakeSettings']['defaults'])
                           ->toHaveKey('theme', 'dark')
                           ->and($cachedData['App\Settings\FakeSettings']['properties'])
                           ->toBe(['theme', 'notifications']);

        File::delete($fakeClassPath);
    });

    test('the settings system boots from the cache file if it exists', function () {
        $path = base_path('bootstrap/cache/typed-settings.php');
        $fakeData = [
            'App\Settings\FakeSettings' => [
                'defaults' => ['theme' => 'fake_from_cache'],
                'properties' => ['theme']
            ]
        ];

        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<?php return ' . var_export($fakeData, true) . ';');

        Settings::bootFromCache();

        $metadata = Settings::getMetadataFor('App\Settings\FakeSettings');
        expect($metadata['defaults'])->toHaveKey('theme', 'fake_from_cache')
                                     ->and($metadata['properties'])->toBe(['theme']);
    });
});
