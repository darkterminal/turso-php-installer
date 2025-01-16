<?php

namespace Turso\PHP\Installer\Services\Sqld;

class EnvironmentFactory
{
    public static function create(): Environment
    {
        return new Environment();
    }
}
