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

test('it casts settings object to json when saving', function () {
    $user = new FeatureUser(['name' => 'Jane Doe']);
    $preferences = new UserPreferences('blue', true, 50);

    $user->preferences = $preferences;
    $user->save();

    $dbValue = DB::table('feature_users')
        ->where('id', $user->id)
        ->value('preferences');

    $decoded = json_decode($dbValue, true);

    expect($decoded['theme'])->toBe('blue')
        ->and($decoded['notifications_enabled'])->toBeTrue()
        ->and($decoded['items_per_page'])->toBe(50);
});

test('it handles null values', function () {
    $user = FeatureUser::create([
        'name' => 'Null User',
        'preferences' => null,
    ]);

    expect($user->preferences)->toBeInstanceOf(UserPreferences::class)
        ->and($user->preferences->theme)->toBe('light');
});

test('it updates settings via object mutation and save', function () {
    $user = FeatureUser::create([
        'name' => 'Updater',
        'preferences' => ['theme' => 'green'],
    ]);

    $prefs = $user->preferences;
    $prefs->theme = 'red';
    $user->preferences = $prefs;
    $user->save();

    $user->refresh();
    expect($user->preferences->theme)->toBe('red');
});

test('it handles frequency enums correctly', function () {
    $user = FeatureUser::create([
        'name' => 'Alert User',
        'preferences' => new UserPreferences(
            frequency: Frequency::Immediate,
        ),
    ]);

    $user->refresh();
    expect($user->preferences->frequency)->toBeInstanceOf(Frequency::class)
                                         ->and($user->preferences->frequency)->toBe(Frequency::Immediate);

    $dbValue = DB::table('feature_users')->where('id', $user->id)->value('preferences');
    expect(json_decode($dbValue, true)['frequency'])->toBe('immediate');
});

test('it can update frequency using enum cases', function () {
    $user = FeatureUser::create([
        'name' => 'Busy User',
        'preferences' => ['frequency' => 'immediate'],
    ]);

    $settings = $user->preferences;
    $settings->frequency = Frequency::Weekly;
    $user->preferences = $settings;
    $user->save();

    expect($user->fresh()->preferences->frequency)->toBe(Frequency::Weekly);
});

test('it fails when an invalid frequency string is provided', function () {
    $this->expectException(ValueError::class);

    FeatureUser::create([
        'name' => 'Hacker User',
        'preferences' => ['frequency' => 'hourly'],
    ]);
});

test('it handles a collection of enums correctly', function () {
    $user = FeatureUser::create([
        'name' => 'Multi-Channel User',
        'preferences' => new UserPreferences(
            channels: [Channel::Email, Channel::Slack]
        ),
    ]);

    $user->refresh();

    expect($user->preferences->channels)->toBeArray()
                                        ->and($user->preferences->channels)->toHaveCount(2)
                                        ->and($user->preferences->channels[0])->toBeInstanceOf(Channel::class)
                                        ->and($user->preferences->channels[1])->toBe(Channel::Slack);

    $dbValue = DB::table('feature_users')->where('id', $user->id)->value('preferences');
    $decoded = json_decode($dbValue, true);

    expect($decoded['channels'])->toBe(['email', 'slack']);
});

test('it can sync collection of enums via array of strings', function () {
    $user = FeatureUser::create([
        'name' => 'Sync User',
        'preferences' => ['channels' => ['email']],
    ]);

    $settings = $user->preferences;
    $settings->channels = [Channel::SMS, Channel::Slack];
    $user->preferences = $settings;
    $user->save();

    $user->refresh();
    expect($user->preferences->channels)->toContain(Channel::SMS)
                                        ->and($user->preferences->channels)->not->toContain(Channel::Email);
});

test('it ignores extra data in the json that is not defined in the class', function () {
    DB::table('feature_users')->insert([
        'name' => 'Messy User',
        'preferences' => json_encode([
            'theme' => 'dark',
            'deprecated_legacy_key' => 'some value'
        ]),
    ]);

    $user = FeatureUser::where('name', 'Messy User')->first();

    expect($user->preferences->theme)->toBe('dark')
                                     ->and(property_exists($user->preferences, 'deprecated_legacy_key'))->toBeFalse();
});

test('it marks the model as dirty when settings are updated', function () {
    $user = FeatureUser::create([
        'name' => 'Dirty Check',
        'preferences' => ['theme' => 'light'],
    ]);

    expect($user->isDirty('preferences'))->toBeFalse();

    $settings = $user->preferences;
    $settings->theme = 'dark';
    $user->preferences = $settings;

    expect($user->isDirty('preferences'))->toBeTrue();
});

test('it coerces numeric strings to integers if the property is typed as int', function () {
    $user = FeatureUser::create([
        'name' => 'Type Coercion',
        'preferences' => ['items_per_page' => '50'],
    ]);

    $user->refresh();

    expect($user->preferences->items_per_page)->toBeInt()->toBe(50);
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

test('it handles nested array data', function () {
    $data = ['theme' => 'dark', 'custom_colors' => ['primary' => '#000', 'secondary' => '#fff']];

    $user = FeatureUser::create([
        'name' => 'Designer',
        'preferences' => $data
    ]);

    expect($user->refresh()->preferences->custom_colors)->toBeArray()
                                                        ->and($user->refresh()->preferences->custom_colors['primary'])->toBe('#000');
});

test('it maintains structural integrity when saving without changes', function () {
    $initialData = [
        'theme' => 'dark',
        'notifications_enabled' => true,
        'items_per_page' => 10,
        'custom_colors' => [],
        'frequency' => 'daily',
        'channels' => ['email']
    ];

    $user = FeatureUser::create(['name' => 'Consistent', 'preferences' => $initialData]);

    $dbValue = DB::table('feature_users')->where('id', $user->id)->value('preferences');

    expect(json_decode($dbValue, true))->toEqual($initialData);
});

test('it handles empty or corrupt json strings by returning default object', function () {
    DB::table('feature_users')->insert([
        'name' => 'Broken User',
        'preferences' => '',
    ]);

    $user = FeatureUser::where('name', 'Broken User')->first();

    expect($user->preferences)->toBeInstanceOf(UserPreferences::class)
                              ->and($user->preferences->theme)->toBe('light');
});

test('it throws an exception when json is corrupt', function () {
    DB::table('feature_users')->insert([
        'name' => 'Broken User',
        'preferences' => '{"theme": "dark"',
    ]);

    $user = FeatureUser::where('name', 'Broken User')->first();

    expect(fn() => $user->preferences)->toThrow(JsonException::class);
});

test('it throws ValueError when one item in a collection is an invalid enum value', function () {
    $this->expectException(ValueError::class);

    FeatureUser::create([
        'name' => 'Bad Collection User',
        'preferences' => [
            'channels' => ['email', 'pigeon_post']
        ],
    ]);
});

test('it throws a TypeError when database types do not match constructor types', function () {
    DB::table('feature_users')->insert([
        'name' => 'Wrong Type User',
        'preferences' => json_encode([
            'channels' => 'not-an-array'
        ]),
    ]);

    $user = FeatureUser::where('name', 'Wrong Type User')->first();

    expect(fn() => $user->preferences)->toThrow(TypeError::class);
});

test('it serializes settings correctly when the model is converted to an array', function () {
    $user = FeatureUser::create([
        'name' => 'API User',
        'preferences' => new UserPreferences(
            frequency: Frequency::Weekly,
            channels: [Channel::Email]
        ),
    ]);

    $modelArray = $user->toArray();

    expect($modelArray['preferences'])->toBeArray()
                                        ->and($modelArray['preferences']['frequency'])->toBe('weekly')
                                        ->and($modelArray['preferences']['channels'])->toBe(['email']);
});

test('it handles nested settings objects correctly', function () {
    $user = FeatureUser::create([
        'name' => 'Secure User',
        'preferences' => [
            'theme' => 'dark',
            'security' => [
                'two_factor_enabled' => true,
                'password_timeout' => 'long'
            ]
        ],
    ]);

    $user->refresh();

    expect($user->preferences->security)->toBeInstanceOf(SecuritySettings::class)
                                        ->and($user->preferences->security->two_factor_enabled)->toBeTrue()
                                        ->and($user->preferences->security->password_timeout)->toBe('long');

    $prefs = $user->preferences;
    $prefs->security->password_timeout = 'never';
    $user->preferences = $prefs;
    $user->save();

    $dbValue = DB::table('feature_users')->where('id', $user->id)->value('preferences');
    $decoded = json_decode($dbValue, true);

    expect($decoded['security']['password_timeout'])->toBe('never');
});

test('it uses default values for nested settings if key is missing', function () {
    $user = FeatureUser::create([
        'name' => 'Default Security User',
        'preferences' => [
            'theme' => 'light',
        ],
    ]);

    expect($user->preferences->security)->toBeInstanceOf(SecuritySettings::class)
                                        ->and($user->preferences->security->two_factor_enabled)->toBeFalse();
});
