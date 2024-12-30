<?php

namespace App\Services\Installation;

use App\Services\Installation\Platforms\LinuxInstaller;
use App\Services\Installation\Platforms\MacInstaller;
use App\Services\Installation\Platforms\WindowsInstaller;

class InstallerFactory
{
    public static function create(): BaseInstaller
    {
        $os = strtolower(php_uname('s'));

        return match (true) {
            str_contains($os, 'darwin') => new MacInstaller,
            str_contains($os, 'linux') => new LinuxInstaller,
            str_contains($os, 'windows') => new WindowsInstaller,
            default => throw new \Exception('Unsupported OS'),
        };
    }
}
