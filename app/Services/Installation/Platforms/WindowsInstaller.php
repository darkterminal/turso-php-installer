<?php

namespace App\Services\Installation\Platforms;

use App\Services\Installation\BaseInstaller;

class WindowsInstaller extends BaseInstaller
{
    public function __construct()
    {
        parent::__construct();
        $this->home_directory = $_SERVER['USERPROFILE'];
        $this->original_extension_name = 'libsql_php.dll';
        $this->extension_name = 'libsql_php.dll';
    }
}
