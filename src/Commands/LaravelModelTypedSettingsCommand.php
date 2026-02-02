<?php

namespace Androlax2\LaravelModelTypedSettings\Commands;

use Illuminate\Console\Command;

class LaravelModelTypedSettingsCommand extends Command
{
    public $signature = 'laravel-model-typed-settings';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
