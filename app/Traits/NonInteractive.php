<?php

namespace App\Traits;

use App\Attributes\AllowedNamespace;
use App\Contracts\Installer;
use App\Traits\Guards\RestrictedTrait;

#[AllowedNamespace(namespaces: 'App\Commands\Installer\InstallTursoExtension')]
trait NonInteractive
{
    use RestrictedTrait;

    public function nonInteractive(Installer $installer): void
    {
        $this->ensureAllowedNamespace();
        
        if ($this->option('no-interaction') || $this->option('n')) {

            if ($this->option('unstable')) {
                $installer->setUnstable(true);
            }

            if ($this->option('thread-safe')) {
                $installer->setThreadSafe();
            }

            if ($this->option('php-ini')) {
                $installer->setPhpIni($this->option('php-ini'));
            }

            if ($this->option('php-version')) {
                $installer->setPhpVersion($this->option('php-version'));
            }

            if ($this->option('extension-dir')) {
                $installer->setExtensionDir($this->option('extension-dir'));
            }

            if ($installer->checkIfAlreadyInstalled()) {
                info(" Turso libSQL Extension for PHP is already installed. Skipping installation... \n Use the `update` command to update the extension.");
                return;
            }

            info('Installing libSQL Extension for PHP...');
            $installer->install();
            info('  ✨ libSQL Extension for PHP installed');

            exit;
        }
    }
}
