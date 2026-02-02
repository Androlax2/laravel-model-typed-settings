<?php

namespace Androlax2\LaravelModelTypedSettings\Tests;

use Androlax2\LaravelModelTypedSettings\LaravelModelTypedSettingsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Androlax2\\LaravelModelTypedSettings\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelModelTypedSettingsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }

    protected function setUpDatabase(): void
    {
        Schema::create('feature_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->settingColumn('preferences');
        });
    }
}
