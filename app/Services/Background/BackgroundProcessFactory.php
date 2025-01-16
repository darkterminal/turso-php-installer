<?php

namespace Turso\PHP\Installer\Services\Background;

use Turso\PHP\Installer\Contracts\Background;

/**
 * Factory class to create a new BackgroundProcess instance.
 */
class BackgroundProcessFactory
{
    /**
     * Create a new BackgroundProcess instance.
     *
     * @return Background
     */
    public static function create(): Background
    {
        return new BackgroundProcess();
    }
}
