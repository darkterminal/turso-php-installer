<?php

namespace App\Services\Background;

use App\Contracts\Background;

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
