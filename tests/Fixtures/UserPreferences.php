<?php

namespace Androlax2\LaravelModelTypedSettings\Tests\Fixtures;

use Androlax2\LaravelModelTypedSettings\Attributes\AsCollection;
use Androlax2\LaravelModelTypedSettings\Settings;

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
        public SecuritySettings $security = new SecuritySettings,
    ) {}
}
