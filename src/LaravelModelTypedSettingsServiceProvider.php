<?php

namespace Androlax2\LaravelModelTypedSettings;

use Illuminate\Database\Schema\Blueprint;
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
            ->name('laravel-model-typed-settings');
    }

    public function packageBooted(): void
    {
        Blueprint::macro('settingColumn', function (string $column = 'settings') {
            /** @var Blueprint $this */
            return $this->json($column)->nullable();
        });
    }
}
