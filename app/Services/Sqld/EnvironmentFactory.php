<?php

namespace App\Services\Sqld;

class EnvironmentFactory
{
    public static function create(): Environment
    {
        return new Environment();
    }
}
