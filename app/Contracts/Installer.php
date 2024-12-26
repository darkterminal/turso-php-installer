<?php

namespace App\Contracts;

interface Installer
{
    public function update(): void;

    public function uninstall(): void;

    public function install(): void;
}
