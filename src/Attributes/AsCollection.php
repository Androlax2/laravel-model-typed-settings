<?php

namespace Androlax2\LaravelModelTypedSettings\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class AsCollection
{
    /**
     * @param class-string $type
     */
    public function __construct(
        public string $type
    ) {}
}
