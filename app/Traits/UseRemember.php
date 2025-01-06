<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;

trait UseRemember
{
    public function rememberInstallationDirectory(): void
    {
        if (!File::exists($this->getMetadataLocationFile())) {
            touch($this->getMetadataLocationFile());
        }
        
        File::put($this->getMetadataLocationFile(), json_encode([
            'version' => $this->getPHPVersion(),
            'nts' => $this->getThreadSafe(),
            'stable' => !$this->getUnstable(),
            'extension_directory' => $this->getExtensionDirToRemember(),
            'php_ini' => $this->getPhpIni(),
        ]));
    }

    abstract protected function getMetadataLocationFile(): string;
    abstract public function getPHPVersion(): string;
    abstract protected function getPhpIni(): string;
    abstract protected function getUnstable(): bool;
    abstract protected function getThreadSafe(): bool;
    abstract protected function getExtensionDirToRemember(): string;
}
