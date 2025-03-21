<?php

namespace Turso\PHP\Installer\Contracts;

interface Installer
{
    public function checkIfAlreadyInstalled(): bool;
    
    public function update(): void;

    public function uninstall(): void;

    public function install(): void;

    public function setUnstable(bool $unstable): void;

    public function setPhpIni(string $php_ini): void;

    public function setPhpVersion(string $php_version): void;

    public function setExtensionDir(string $extension_dir): void;

    public function setThreadSafe(): void;
}
