<?php

namespace Turso\PHP\Installer\Traits;

use Turso\PHP\Installer\Attributes\AllowedNamespace;
use Turso\PHP\Installer\Contracts\Installer;
use Turso\PHP\Installer\Traits\Guards\RestrictedTrait;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AllowedNamespace(namespaces: 'Turso\PHP\Installer\Commands\Installer\InstallTursoExtension')]
trait HasInstallQuestions
{
    use RestrictedTrait;

    private bool $usingStableVersion;

    public function askQuestions(Installer $installer): void
    {
        $this->ensureAllowedNamespace();

        $this->isUsingStableVersion($installer)
            ->isUsingThreadSafeVersion($installer)
            ->isUsingPhpIni($installer)
            ->isUsingCustomExtensionDir($installer)
            ->isUsingPhpVersion($installer);

        $installer->install();
        exit;
    }

    private function isUsingStableVersion(Installer $installer): self
    {
        $this->usingStableVersion = confirm(
            label: 'Install the stable or unstable version of the extension?',
            default: true,
            yes: 'Stable',
            no: 'Unstable',
            hint: 'Stable = Production, Unstable = Development, default: Stable'
        );
        $installer->setUnstable(!$this->usingStableVersion);

        return $this;
    }

    private function isUsingThreadSafeVersion(Installer $installer): self
    {
        $usingThreadSafeVersion = confirm(
            label: 'Install the Thread Safe (TS) or Non-Thread Safe (NTS) version?',
            default: false,
            yes: 'TS',
            no: 'NTS',
            hint: 'TS = Thread Safe, NTS = Non-Thread Safe, default: TS '
        );
        if ($usingThreadSafeVersion) {
            $installer->setThreadSafe();
        }

        return $this;
    }

    private function isUsingPhpIni(Installer $installer): self
    {
        $usingCustomPhpIni = confirm(
            label: 'Use a custom php.ini file?',
            default: false,
            yes: 'Yes',
            no: 'No',
            hint: 'default: No, using ' . get_php_ini_file()
        );
        if ($usingCustomPhpIni) {
            $phpIniField = text(
                label: 'Enter the path to the php.ini file',
                placeholder: '/path/to/your/php.ini',
                hint: 'default: ' . get_php_ini_file()
            );
            if (!empty($phpIniField)) {
                $installer->setPhpIni(get_php_ini_file());
            }
        }

        return $this;
    }

    private function isUsingCustomExtensionDir(Installer $installer): self
    {
        $usingCustomExtensionDir = confirm(
            label: 'Use a custom PHP extension directory?',
            default: false,
            yes: 'Yes',
            no: 'No',
            hint: 'default: No, using ' . get_version_installation_dir($this->usingStableVersion)
        );
        if ($usingCustomExtensionDir) {
            $extensionDirField = text(
                label: 'Enter the path to the PHP extension directory',
                placeholder: '/path/to/your/extensions/directory',
                hint: 'default: ' . get_version_installation_dir($this->usingStableVersion)
            );
            if (!empty($extensionDirField)) {
                $installer->setExtensionDir(get_version_installation_dir($this->usingStableVersion));
            }
        }

        return $this;
    }

    private function isUsingPhpVersion(Installer $installer): self
    {
        $choosePhpVersion = select(
            label: 'Choose the PHP version',
            options: [
                '8.1' => 'PHP 8.1',
                '8.2' => 'PHP 8.2',
                '8.3' => 'PHP 8.3',
                '8.4' => 'PHP 8.4',
            ],
            default: get_current_php_version(),
            hint: "default: " . get_current_php_version() . " follow the current " . get_php_ini_file()
        );
        if ($choosePhpVersion === get_current_php_version()) {
            $installer->setPhpVersion($choosePhpVersion);
        } else {
            $overwrite = confirm(
                label: 'Are you sure you want to continue?',
                default: false,
                yes: 'Yes',
                no: 'No',
                hint: "The current PHP version is " . get_current_php_version() . ", but you selected $choosePhpVersion. default: No"
            );
            if ($overwrite) {
                $replace_current_version = str_replace(
                    get_current_php_version(),
                    $choosePhpVersion,
                    get_php_ini_file()
                );

                $replace_php_version_extension_dir = str_replace(
                    get_current_php_version(),
                    $choosePhpVersion,
                    get_version_installation_dir($this->usingStableVersion)
                );

                $installer->setPhpIni($replace_current_version);
                $installer->setExtensionDir($replace_php_version_extension_dir);
            }
        }

        return $this;
    }
}
