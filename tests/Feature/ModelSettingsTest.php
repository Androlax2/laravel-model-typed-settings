<?php

use Androlax2\LaravelModelTypedSettings\Attributes\AsCollection;
use Androlax2\LaravelModelTypedSettings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

enum Frequency: string
{
    case Immediate = 'immediate';
    case Daily = 'daily';
    case Weekly = 'weekly';
}

enum Channel: string
{
    case Email = 'email';
    case SMS = 'sms';
    case Slack = 'slack';
}

class SecuritySettings extends Settings
{
    public function __construct(
        public bool $two_factor_enabled = false,
        public string $password_timeout = 'short'
    ) {}
}

class UserPreferences extends Settings
{
    public function __construct(
        public string $theme = 'light',
        public bool $notifications_enabled = true,
        public int $items_per_page = 10,
        public array $custom_colors = [],
        public Frequency $frequency = Frequency::Daily,
        #[AsCollection(Channel::class)]
        public array $channels = [Channel::Email],
        public SecuritySettings $security = new SecuritySettings(),
    ) {}
}

class FeatureUser extends Model
{
    protected $guarded = [];

    protected $table = 'feature_users';

    public $timestamps = false;

    protected $casts = [
        'preferences' => UserPreferences::class,
    ];
}

beforeEach(function () {
    Schema::create('feature_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->settingColumn('preferences');
    });
});

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
