<?php

use Androlax2\LaravelModelTypedSettings\Settings;
use Androlax2\LaravelModelTypedSettings\Tests\Fixtures\Channel;
use Androlax2\LaravelModelTypedSettings\Tests\Fixtures\FeatureUser;
use Androlax2\LaravelModelTypedSettings\Tests\Fixtures\Frequency;
use Androlax2\LaravelModelTypedSettings\Tests\Fixtures\SecuritySettings;
use Androlax2\LaravelModelTypedSettings\Tests\Fixtures\UserPreferences;
use Illuminate\Support\Facades\DB;

describe('Core Hydration & Defaults', function () {
    test('it casts json to settings object', function () {
        $user = FeatureUser::create([
            'name' => 'John Doe',
            'preferences' => [
                'theme' => 'dark',
                'notifications_enabled' => false,
                'items_per_page' => 20,
            ],
        ]);

        $user->refresh();

        expect($user->preferences)->toBeInstanceOf(UserPreferences::class)
                                  ->and($user->preferences->theme)->toBe('dark')
                                  ->and($user->preferences->notifications_enabled)->toBeFalse()
                                  ->and($user->preferences->items_per_page)->toBe(20);
    });

    test('it uses default values for keys missing in the database', function () {
        DB::table('feature_users')->insert([
            'name' => 'Old User',
            'preferences' => json_encode(['theme' => 'dark']),
        ]);

        $user = FeatureUser::where('name', 'Old User')->first();

        expect($user->preferences->theme)->toBe('dark')
                                         ->and($user->preferences->items_per_page)->toBe(10);
    });

    test('it coerces numeric strings to integers if the property is typed as int', function () {
        $user = FeatureUser::create([
            'name' => 'Type Coercion',
            'preferences' => ['items_per_page' => '50'],
        ]);

        expect($user->refresh()->preferences->items_per_page)->toBeInt()->toBe(50);
    });

    test('it ignores extra data in the json that is not defined in the class', function () {
        DB::table('feature_users')->insert([
            'name' => 'Messy User',
            'preferences' => json_encode(['theme' => 'dark', 'unknown_key' => 'value']),
        ]);

        $user = FeatureUser::where('name', 'Messy User')->first();

        expect($user->preferences->theme)->toBe('dark')
                                         ->and(property_exists($user->preferences, 'unknown_key'))->toBeFalse();
    });
});

describe('Enum Handling', function () {
    test('it handles frequency enums correctly', function () {
        $user = FeatureUser::create([
            'name' => 'Alert User',
            'preferences' => new UserPreferences(frequency: Frequency::Immediate),
        ]);

        $user->refresh();
        expect($user->preferences->frequency)->toBe(Frequency::Immediate);

        $dbValue = DB::table('feature_users')->where('id', $user->id)->value('preferences');
        expect(json_decode($dbValue, true)['frequency'])->toBe('immediate');
    });

    test('it fails when an invalid frequency string is provided', function () {
        $this->expectException(ValueError::class);

        FeatureUser::create([
            'name' => 'Hacker User',
            'preferences' => ['frequency' => 'hourly'],
        ]);
    });
});

describe('Collections', function () {
    test('it handles a collection of enums correctly', function () {
        $user = FeatureUser::create([
            'name' => 'Multi-Channel User',
            'preferences' => new UserPreferences(channels: [Channel::Email, Channel::Slack]),
        ]);

        $user->refresh();

        expect($user->preferences->channels)->toBeArray()
                                            ->and($user->preferences->channels[1])->toBe(Channel::Slack);
    });

    test('it throws ValueError when one item in a collection is an invalid enum value', function () {
        $this->expectException(ValueError::class);

        FeatureUser::create([
            'name' => 'Bad Collection User',
            'preferences' => ['channels' => ['email', 'pigeon_post']],
        ]);
    });
});

describe('Nested Settings', function () {
    test('it handles nested settings objects correctly', function () {
        $user = FeatureUser::create([
            'name' => 'Secure User',
            'preferences' => [
                'security' => ['two_factor_enabled' => true]
            ],
        ]);

        expect($user->refresh()->preferences->security)->toBeInstanceOf(SecuritySettings::class)
                                                       ->and($user->preferences->security->two_factor_enabled)->toBeTrue();
    });

    test('it uses default values for nested settings if key is missing', function () {
        $user = FeatureUser::create(['name' => 'Default Security User', 'preferences' => []]);

        expect($user->preferences->security)->toBeInstanceOf(SecuritySettings::class)
                                            ->and($user->preferences->security->two_factor_enabled)->toBeFalse();
    });
});

describe('Eloquent Integration & Serialization', function () {
    test('it marks the model as dirty when settings are updated', function () {
        $user = FeatureUser::create(['name' => 'Dirty Check', 'preferences' => []]);

        $settings = $user->preferences;
        $settings->theme = 'dark';
        $user->preferences = $settings;

        expect($user->isDirty('preferences'))->toBeTrue();
    });

    test('it serializes correctly when model is converted to array', function () {
        $user = FeatureUser::create([
            'name' => 'API User',
            'preferences' => new UserPreferences(frequency: Frequency::Weekly),
        ]);

        expect($user->toArray()['preferences']['frequency'])->toBe('weekly');
    });

    test('it handles empty or corrupt json strings', function () {
        DB::table('feature_users')->insert(['name' => 'Empty', 'preferences' => '']);
        $user = FeatureUser::where('name', 'Empty')->first();
        expect($user->preferences)->toBeInstanceOf(UserPreferences::class);

        DB::table('feature_users')->insert(['name' => 'Broken', 'preferences' => '{bad:json}']);
        $userBroken = FeatureUser::where('name', 'Broken')->first();
        expect(fn() => $userBroken->preferences)->toThrow(JsonException::class);
    });
});

describe('Lean JSON Storage (Stripping Defaults)', function () {
    test('it does not save values to the database if they match defaults', function () {
        $user = FeatureUser::create([
            'name' => 'Lean User',
            'preferences' => [
                'theme' => 'light',
                'items_per_page' => 99
            ],
        ]);

        $dbValue = DB::table('feature_users')->where('id', $user->id)->value('preferences');
        $decoded = json_decode($dbValue, true);

        expect($decoded)->not->toHaveKey('theme')
                             ->and($decoded)->toHaveKey('items_per_page', 99);
    });

    test('it strips defaults recursively in nested settings', function () {
        $user = FeatureUser::create([
            'name' => 'Nested Lean User',
            'preferences' => [
                'security' => [
                    'two_factor_enabled' => false,
                    'password_timeout' => 'infinite'
                ]
            ],
        ]);

        $dbValue = DB::table('feature_users')->where('id', $user->id)->value('preferences');
        $decoded = json_decode($dbValue, true);

        expect($decoded['security'])->not->toHaveKey('two_factor_enabled')
                                         ->and($decoded['security'])->toHaveKey('password_timeout', 'infinite');
    });

    test('it saves an empty json object if all values are defaults', function () {
        $user = FeatureUser::create([
            'name' => 'Default Only User',
            'preferences' => new UserPreferences(),
        ]);

        $dbValue = DB::table('feature_users')->where('id', $user->id)->value('preferences');

        expect(json_decode($dbValue, true))->toBeEmpty();
    });

    test('it restores defaults correctly when reading from a stripped JSON', function () {
        DB::table('feature_users')->insert([
            'name' => 'Manual Stripped',
            'preferences' => json_encode(['items_per_page' => 5])
        ]);

        $user = FeatureUser::where('name', 'Manual Stripped')->first();

        expect($user->preferences->theme)->toBe('light')
                                         ->and($user->preferences->items_per_page)->toBe(5);
    });
});

describe('Optimization & Tooling', function () {
    test('it uses the pre-reflected property list when available', function () {
        $cache = [
            UserPreferences::class => [
                'properties' => ['theme', 'notifications_enabled']
            ]
        ];

        Settings::setMetadataCache($cache);

        expect(Settings::getMetadataFor(UserPreferences::class))
            ->toHaveKey('properties')
            ->and(Settings::getMetadataFor(UserPreferences::class)['properties'])
            ->toContain('theme');
    });

    test('it still works normally if the cache is empty', function () {
        Settings::setMetadataCache([]);

        $user = new UserPreferences(theme: 'emerald');

        expect($user->theme)->toBe('emerald');
    });
});
