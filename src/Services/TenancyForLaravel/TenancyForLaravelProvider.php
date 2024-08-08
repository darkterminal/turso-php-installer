<?php

declare(strict_types=1);

namespace Darkterminal\TursoLibSQLInstaller\Services\TenancyForLaravel;

use Exception;

final class TenancyForLaravelProvider
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function isLaravelProject(): bool
    {
        $requiredFiles = [
            'artisan',
            'composer.json',
            'app',
            'bootstrap',
            'config',
            'database',
            'routes',
            'resources',
        ];

        foreach ($requiredFiles as $file) {
            if (!file_exists($this->projectDir . DIRECTORY_SEPARATOR . $file)) {
                return false;
            }
        }

        return true;
    }

    public function replaceTenantModel(string $filePath, string $newModel): true|Exception
    {
        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            throw new Exception("Failed to read the file.");
        }

        $pattern = "/'tenant_model'\s*=>\s*[^,]+,/";
        $replacement = "'tenant_model' => $newModel,";
        $newFileContents = preg_replace($pattern, $replacement, $fileContents);

        if ($newFileContents === null) {
            throw new Exception("Failed to replace the tenant_model value.");
        }

        if (file_put_contents($filePath, $newFileContents) === false) {
            throw new Exception("Failed to write to the file.");
        }

        return true;
    }

    public function replaceDatabasePrefixSuffix(string $filePath, string $newPrefix, string $newSuffix): true|Exception
    {
        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            throw new Exception("Failed to read the file.");
        }

        $prefixPattern = "/'prefix'\s*=>\s*[^,]+,/";
        $suffixPattern = "/'suffix'\s*=>\s*[^,]+,/";
        $prefixReplacement = "'prefix' => '$newPrefix',";
        $suffixReplacement = "'suffix' => '$newSuffix',";
        $newFileContents = preg_replace($prefixPattern, $prefixReplacement, $fileContents);
        $newFileContents = preg_replace($suffixPattern, $suffixReplacement, $newFileContents);

        if ($newFileContents === null) {
            throw new Exception("Failed to replace the prefix or suffix value.");
        }

        if (file_put_contents($filePath, $newFileContents) === false) {
            throw new Exception("Failed to write to the file.");
        }

        return true;
    }

    public function fileArrayAppend(string $target, string $key, string $value): true|Exception
    {
        $fileLines = file($target, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($fileLines === false) {
            throw new Exception("Failed to read the file.");
        }

        $insideTargetArray = false;
        $arrayIndentation = '';

        foreach ($fileLines as $index => $line) {
            if (strpos($line, "'{$key}' => [") !== false) {
                $insideTargetArray = true;
                $arrayIndentation = str_repeat(' ', strpos($line, "'{$key}' => [") + 1);
            }

            if ($insideTargetArray && strpos($line, $value) !== false) {
                continue;
            }

            if ($insideTargetArray && strpos($line, '],') !== false) {
                array_splice($fileLines, $index, 0, $arrayIndentation . '    ' . $value);
                break;
            }
        }

        if (file_put_contents($target, implode(PHP_EOL, $fileLines)) === false) {
            throw new Exception("Failed to write to the file.\n");
        }

        return true;
    }

    public function addValueToArrayInFile(string $filePath, string|array $values): true|Exception
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        if (!file_exists($filePath)) {
            throw new Exception("File does not exist.");
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            throw new Exception("Failed to read the file.");
        }

        $position = strrpos($fileContent, '];');
        if ($position === false) {
            throw new Exception("Invalid array format in the file.");
        }

        preg_match_all("/\s*([a-zA-Z0-9_\\\\]+::class),/", $fileContent, $matches);
        $currentValues = $matches[1];

        $newValues = '';
        foreach ($values as $value) {
            $cleanValue = rtrim($value, ',');
            if (in_array($cleanValue, $currentValues)) {
                continue;
            }
            $newValues .= '    ' . $cleanValue . ',' . PHP_EOL;
        }

        $updatedContent = substr($fileContent, 0, $position) . $newValues . substr($fileContent, $position);

        if (file_put_contents($filePath, $updatedContent) === false) {
            throw new Exception("Failed to write to the file.");
        }

        return true;
    }

    private function runShellCommand(string $command): array
    {
        $output = [];
        $returnVar = 0;
        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Command failed: " . implode("\n", $output));
        }

        return $output;
    }

    public function installTenancy(): void
    {
        if (!$this->isLaravelProject()) {
            echo "'turso-php-installer add:tenancy-for-laravel' command is only for Laravel Project\n";
            return;
        }

        try {
            echo "Installing stancl/tenancy...\n";
            $composerOutput = $this->runShellCommand('/usr/bin/env php -d allow_url_fopen=On -d memory_limit=-1 $(which composer) require stancl/tenancy');
            echo "Composer output: " . implode("\n", $composerOutput) . "\n";

            echo "Installing tenancy...\n";
            sleep(2);
            $artisanOutput = $this->runShellCommand('/usr/bin/env php -d allow_url_fopen=On -d memory_limit=-1 artisan tenancy:install');
            echo "Artisan output: " . implode("\n", $artisanOutput) . "\n";
            sleep(2);

            echo "Installing tursodatabase/turso-driver-laravel...\n";
            sleep(2);
            $composerOutput = $this->runShellCommand('/usr/bin/env php -d allow_url_fopen=On -d memory_limit=-1 $(which composer) require tursodatabase/turso-driver-laravel');
            echo "Composer output: " . implode("\n", $composerOutput) . "\n";
            sleep(2);

            $this->addValueToArrayInFile("{$this->projectDir}bootstrap/providers.php", [
                'App\Providers\TenancyServiceProvider::class',
                'Turso\Driver\Laravel\LibSQLDriverServiceProvider::class',
            ]);

            echo "Create a Command Overrider\n";
            $commandDir = "{$this->projectDir}app/Console/Commands";
            $commandFile = $commandDir . '/TursoMigrateFresh.php';
            $commandContent = file_get_contents(__DIR__ . '/Console/Commands/TursoMigrateFresh.php');
            if (!is_dir($commandDir)) {
                mkdir($commandDir, 0755, true);
            }
            file_put_contents($commandFile, $commandContent);
            sleep(2);

            echo "Create a Tenant models\n";
            $tenantModelStubs = __DIR__ . '/Models/Tenant.php';
            $tenantModelContent = file_get_contents($tenantModelStubs);
            $createTenantModel = "{$this->projectDir}app/Models/Tenant.php";
            file_put_contents($createTenantModel, $tenantModelContent);
            sleep(2);

            echo "Create a Turso Database Manager and Boostrapper\n";
            $databaseManagerStubs = __DIR__ . '/TursoTenancy/TursoDatabaseManager.php';
            $databaseManagerContent = file_get_contents($databaseManagerStubs);
            $bootstrapperStubs = __DIR__ . '/TursoTenancy/TursoTenancyBootstrapper.php';
            $bootstrapperContent = file_get_contents($bootstrapperStubs);

            $tursoTenancyDir = "{$this->projectDir}app/TursoTenancy";
            if (!is_dir($tursoTenancyDir)) {
                mkdir($tursoTenancyDir, 0755, true);
            }

            $createDatabaseManager = $tursoTenancyDir . '/TursoDatabaseManager.php';
            file_put_contents($createDatabaseManager, $databaseManagerContent);
            sleep(2);

            $createBootstrapper = $tursoTenancyDir . '/TursoTenancyBootstrapper.php';
            file_put_contents($createBootstrapper, $bootstrapperContent);
            sleep(2);

            echo "Configure Database Manager & Bootstrapper\n";
            $configFilePath = "{$this->projectDir}config/tenancy.php";
            $this->replaceTenantModel($configFilePath, '\App\Models\Tenant::class');
            $this->replaceDatabasePrefixSuffix($configFilePath, 'tenant_', '.sqlite');
            $this->fileArrayAppend($configFilePath, 'bootstrappers', 'App\TursoTenancy\TursoTenancyBootstrapper::class,');
            $this->fileArrayAppend($configFilePath, 'managers', "'libsql' => App\TursoTenancy\TursoDatabaseManager::class,");

        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
