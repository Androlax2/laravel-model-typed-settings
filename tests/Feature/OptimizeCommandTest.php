<?php

use Androlax2\LaravelModelTypedSettings\Settings;

beforeEach(function () {
    $path = base_path('bootstrap/cache/typed-settings.php');
    if (File::exists($path)) {
        File::delete($path);
    }
});

describe('Artisan Optimization Command', function () {
    test('the settings:cache command generates a cache file', function () {
        $fakeClassPath = app_path('Settings/FakeSettings.php');
        File::ensureDirectoryExists(dirname($fakeClassPath));

        File::put($fakeClassPath, <<<PHP
<?php
namespace App\Settings;
use Androlax2\LaravelModelTypedSettings\Settings;

class FakeSettings extends Settings {
    public string \$theme = 'dark';
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
                           ->toHaveKey('theme', 'dark');

        File::delete($fakeClassPath);
    });

    test('the settings system boots from the cache file if it exists', function () {
        $path = base_path('bootstrap/cache/typed-settings.php');
        $fakeData = [
            'App\Settings\FakeSettings' => [
                'defaults' => ['theme' => 'fake_from_cache']
            ]
        ];

        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<?php return ' . var_export($fakeData, true) . ';');

        Settings::bootFromCache();

        expect(Settings::getMetadataFor('App\Settings\FakeSettings')['defaults'])
            ->toHaveKey('theme', 'fake_from_cache');
    });
});
