<?php

namespace App\Services\LibsqlServer;

/**
 * Factory class to create an instance of LibsqlServerGenerator.
 *
 * This class is used to create an instance of LibsqlServerGenerator.
 * The create method returns a new instance of LibsqlServerGenerator.
 */
class LibsqlServerFactory
{
    /**
     * Create a new instance of LibsqlServerGenerator.
     *
     * @return LibsqlServerGenerator
     */
    public static function create(): LibsqlServerGenerator
    {
        return new LibsqlServerGenerator();
    }
}
