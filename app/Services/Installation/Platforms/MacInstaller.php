<?php

namespace App\Services\Installation\Platforms;

use App\Services\Installation\BaseInstaller;

class MacInstaller extends BaseInstaller
{
    public function __construct()
    {
        $this->home_directory = $_SERVER['HOME'];
        $this->original_extension_name = 'liblibsql_php.dylib';
        parent::__construct();
    }
}
