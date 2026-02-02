<?php

use Androlax2\LaravelModelTypedSettings\Settings;
use Androlax2\LaravelModelTypedSettings\Tests\Fixtures\UserPreferences;

beforeEach(function () {
    $path = base_path('bootstrap/cache/typed-settings.php');
    if (File::exists($path)) {
        File::delete($path);
    }
});

describe('Artisan Optimization Command', function () {
    test('the settings:cache command generates a cache file', function () {
        Artisan::call('settings:cache');

        $path = base_path('bootstrap/cache/typed-settings.php');

        expect(File::exists($path))->toBeTrue();

        $cachedData = require $path;
        expect($cachedData)->toHaveKey(UserPreferences::class)
                           ->and($cachedData[UserPreferences::class]['properties'])->toContain('theme');
    });

    test('the settings system boots from the cache file if it exists', function () {
        $path = base_path('bootstrap/cache/typed-settings.php');
        $fakeData = [
            UserPreferences::class => [
                'properties' => ['fake_property_from_cache']
            ]
        ];

        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<?php return ' . var_export($fakeData, true) . ';');

        Settings::bootFromCache();

        expect(Settings::getMetadataFor(UserPreferences::class)['properties'])
            ->toContain('fake_property_from_cache');
    });

    test('settings:clear removes the cache file', function () {
        Artisan::call('settings:cache');
        expect(File::exists(base_path('bootstrap/cache/typed-settings.php')))->toBeTrue();

        Artisan::call('settings:clear');
        expect(File::exists(base_path('bootstrap/cache/typed-settings.php')))->toBeFalse();
    });
});

describe('Framework Integration', function () {
    test('it triggers settings:cache when artisan optimize is called', function () {
        Artisan::spy();

        Artisan::call('optimize');

        Artisan::shouldHaveReceived('call')
               ->with('settings:cache');
    });

    test('it cleans up settings cache when artisan optimize:clear is called', function () {
        Artisan::spy();

        Artisan::call('optimize:clear');

        Artisan::shouldHaveReceived('call')
               ->with('settings:clear');
    });
});
