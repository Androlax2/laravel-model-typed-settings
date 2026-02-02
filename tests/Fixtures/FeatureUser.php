<?php

namespace Androlax2\LaravelModelTypedSettings\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class FeatureUser extends Model
{
    protected $guarded = [];

    protected $table = 'feature_users';

    public $timestamps = false;

    protected $casts = [
        'preferences' => UserPreferences::class,
    ];
}
