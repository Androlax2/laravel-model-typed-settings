<?php

use Androlax2\LaravelModelTypedSettings\Casts\GenericSettingsBridge;
use Androlax2\LaravelModelTypedSettings\Settings;
use Illuminate\Database\Eloquent\Model;

class TestProfileSettings extends Settings
{
    public function __construct(
        public string $theme = 'light',
        public bool $notifications = false
    ) {}
}

class TestUser extends Model
{
    protected $guarded = [];

    protected $table = 'users';

    protected $casts = [
        'settings' => TestProfileSettings::class,
    ];
}

test('settings object can be created from array', function () {
    $settings = TestProfileSettings::fromArray(['theme' => 'dark', 'notifications' => true]);

    expect($settings)->toBeInstanceOf(TestProfileSettings::class)
        ->and($settings->theme)->toBe('dark')
        ->and($settings->notifications)->toBeTrue();
});

test('settings object can be converted to array', function () {
    $settings = new TestProfileSettings('dark', true);

    expect($settings->toArray())->toBe(['theme' => 'dark', 'notifications' => true]);
});

test('generic settings bridge casts attributes correctly', function () {
    $cast = new GenericSettingsBridge(TestProfileSettings::class);
    $model = new TestUser;

    $settings = new TestProfileSettings('dark', true);
    $json = $cast->set($model, 'settings', $settings, []);

    expect($json)->toBeJson()
        ->and(json_decode($json, true))->toBe(['theme' => 'dark', 'notifications' => true]);

    $restored = $cast->get($model, 'settings', $json, []);
    expect($restored)->toBeInstanceOf(TestProfileSettings::class)
        ->and($restored->theme)->toBe('dark')
        ->and($restored->notifications)->toBeTrue();
});

test('eloquent cast logic works', function () {
    $user = new TestUser;

    $casts = $user->getCasts();

    expect($casts)->toHaveKey('settings')
        ->and($casts['settings'])->toBe(TestProfileSettings::class);
});
