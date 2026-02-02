<?php

namespace Androlax2\LaravelModelTypedSettings;

use Androlax2\LaravelModelTypedSettings\Commands\LaravelModelTypedSettingsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelModelTypedSettingsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-model-typed-settings')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_model_typed_settings_table')
            ->hasCommand(LaravelModelTypedSettingsCommand::class);
    }
}
