<?php

namespace App\Services\Installation\Platforms;

use App\Services\Installation\BaseInstaller;
use Illuminate\Support\Facades\File;

class LinuxInstaller extends BaseInstaller
{
    protected bool $is_docker = false;

    public function __construct()
    {
        $this->home_directory = $_SERVER['HOME'];
        $this->is_docker = File::exists('/.dockerenv');
        parent::__construct();
    }

    protected function extensionDirectory(): string
    {
        if ($this->is_docker) {
            return '/root/.turso-client-php';
        }

        return parent::extensionDirectory();
    }
}
