<?php

namespace Turso\PHP\Installer\Traits;

use Illuminate\Support\Facades\File;

trait UseRemember
{
    public function rememberInstallationDirectory(): void
    {
        $metadataFile = $this->getMetadataLocationFile();
        $metadataDir = dirname($metadataFile);

        if (!File::exists($metadataDir)) {
            File::makeDirectory($metadataDir, 0755, true);
        }

        if (!File::exists($metadataFile)) {
            touch($metadataFile);
        }
        
        File::put($this->getMetadataLocationFile(), json_encode([
            'version' => $this->getPHPVersion(),
            'nts' => $this->getThreadSafe(),
            // Only the stable channel exists. The key is kept so older
            // metadata readers keep working.
            'stable' => true,
            'extension_directory' => $this->getExtensionDirToRemember(),
            'php_ini' => $this->getPhpIni(),
            'cert_store_location' => $this->getCertStoreLocation(),
        ]));
    }

    abstract protected function getMetadataLocationFile(): string;
    abstract public function getPHPVersion(): string;
    abstract protected function getPhpIni(): string;
    abstract protected function getThreadSafe(): bool;
    abstract protected function getExtensionDirToRemember(): string;
    abstract protected function getCertStoreLocation(): string;
}
