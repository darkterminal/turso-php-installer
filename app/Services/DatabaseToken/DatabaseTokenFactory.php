<?php

namespace App\Services\DatabaseToken;

/**
 * Class DatabaseTokenFactory.
 *
 * This class is a factory used to create an instance of DatabaseTokenGenerator.
 */
class DatabaseTokenFactory
{
    /**
     * Create a new instance of DatabaseTokenGenerator.
     *
     * @return DatabaseTokenGenerator
     */
    public static function create(): DatabaseTokenGenerator
    {
        return new DatabaseTokenGenerator();
    }
}
