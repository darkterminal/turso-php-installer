<?php

namespace App\Services\Installation\Platforms;

use App\Services\Installation\BaseInstaller;
use Illuminate\Support\Facades\File;

class LinuxInstaller extends BaseInstaller
{
    protected bool $is_docker = false;

    public function __construct()
    {
        parent::__construct();
        $this->home_directory = $_SERVER['HOME'];
    }

    protected function extensionDirectory(): string
    {
        return parent::extensionDirectory();
    }
}
