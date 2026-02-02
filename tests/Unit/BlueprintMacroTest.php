<?php

namespace Androlax2\LaravelModelTypedSettings\Tests\Unit;

use Androlax2\LaravelModelTypedSettings\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BlueprintMacroTest extends TestCase
{
    public function test_setting_column_macro_is_registered(): void
    {
        $this->assertTrue(Blueprint::hasMacro('settingColumn'));
    }

    public function test_setting_column_macro_creates_column(): void
    {
        Schema::create('macro_test_table', function (Blueprint $table) {
            $table->id();
            $table->settingColumn('user_settings');
        });

        $this->assertTrue(Schema::hasColumn('macro_test_table', 'user_settings'));
    }
}
