<?php

namespace Androlax2\LaravelModelTypedSettings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Androlax2\LaravelModelTypedSettings\LaravelModelTypedSettings
 */
class LaravelModelTypedSettings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Androlax2\LaravelModelTypedSettings\LaravelModelTypedSettings::class;
    }
}
