<?php

namespace Turso\PHP\Installer\Services\Installation\Platforms;

use Turso\PHP\Installer\Services\Installation\BaseInstaller;

class MacInstaller extends BaseInstaller
{
    public function __construct()
    {
        parent::__construct();
        $this->home_directory = $_SERVER['HOME'];
        $this->original_extension_name = 'liblibsql_php.dylib';
    }
}
