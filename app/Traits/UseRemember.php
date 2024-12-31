<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;

trait UseRemember
{
    public function rememberInstallationDirectory(): void
    {
        File::put($this->getMetadataLocationFile(), json_encode([
            'version' => $this->getPHPVersion(),
            'nts' => $this->getNonThreadSafe(),
            'stable' => !$this->getUnstable(),
            'extension_directory' => $this->getExtensionDirToRemember(),
            'php_ini' => $this->getPhpIni(),
        ]));
    }

    abstract protected function getMetadataLocationFile(): string;
    abstract public function getPHPVersion(): string;
    abstract protected function getPhpIni(): string;
    abstract protected function getUnstable(): bool;
    abstract protected function getNonThreadSafe(): bool;
    abstract protected function getExtensionDirToRemember(): string;
}
