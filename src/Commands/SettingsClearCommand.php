<?php

namespace Androlax2\LaravelModelTypedSettings\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SettingsClearCommand extends Command
{
    protected $signature = 'settings:clear';

    public function handle(): void
    {
        $path = $this->laravel->bootstrapPath('cache/typed-settings.php');

        if (! File::exists($path)) {
            return;
        }

        File::delete($path);
        $this->info('Typed settings cache cleared from bootstrap/cache.');
    }
}
