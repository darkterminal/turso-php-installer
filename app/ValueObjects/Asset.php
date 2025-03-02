<?php

namespace Turso\PHP\Installer\ValueObjects;

use Turso\PHP\Installer\Handlers\ZipHandler;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\File;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Str;
use GuzzleHttp\Middleware;
use GuzzleHttp\Client;
use RuntimeException;
use PharData;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class Asset
{
    public string $name;

    public string $download_url;

    private string $download_path;

    private string $extracted_path;

    public string $temporary_name;

    public static function from(array $array)
    {
        $asset = new self;
        $asset->name = $array['name'];
        $asset->download_url = $array['browser_download_url'];

        $tempDir = sys_get_temp_dir();
        if (File::exists('/home/sail')) {
            $tempDir = getenv('HOME') . '/.tmp';
            if (!File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }
        }

        $asset->download_path = $tempDir;
        $asset->extracted_path = $asset->download_path . DIRECTORY_SEPARATOR . $asset->filename();
        $asset->temporary_name = Str::random(40);

        return $asset;
    }

    public function download()
    {
        $client = $this->createRetryClient(3, 100);
        $tempFile = $this->download_path . DIRECTORY_SEPARATOR . $this->getTempName();

        spin(
            message: 'Downloading Turso/libSQL Extension for PHP...',
            callback: function () use ($client, $tempFile) {
                try {
                    $response = $client->get($this->download_url, [
                        'sink' => $tempFile
                    ]);

                    if ($response->getStatusCode() !== 200) {
                        throw new RuntimeException('Download failed with status: ' . $response->getStatusCode());
                    }

                    return $response;
                } catch (GuzzleException $e) {
                    throw new RuntimeException('Download error: ' . $e->getMessage());
                }
            }
        );
    }

    private function createRetryClient(int $maxRetries, int $delayMs): Client
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            function ($retries, $request, $response, $exception) use ($maxRetries) {
                return $retries < $maxRetries && ($exception || ($response && $response->getStatusCode() >= 500));
            },
            function ($retries) use ($delayMs) {
                return $delayMs * 1000;
            }
        ));

        return new Client(['handler' => $stack]);
    }

    public function extract(string $extractTo, array $only = [])
    {
        $archiver = $this->getArchiver();

        $archiver->extractTo($this->download_path, null, true);
        $dir = pathinfo(path: $this->download_path . DIRECTORY_SEPARATOR . $this->getTempName());

        foreach ($only as $fileNameToExtract) {
            info(sprintf('  ✅ Extracted %s', $fileNameToExtract));
            if ($archiver instanceof ZipHandler) {
                $windowsExtractedPath = $this->download_path . DIRECTORY_SEPARATOR . $dir['filename'];
                if (is_dir($windowsExtractedPath) && file_exists($windowsExtractedPath . DIRECTORY_SEPARATOR . $fileNameToExtract)) {
                    File::move(
                        $windowsExtractedPath . DIRECTORY_SEPARATOR . $fileNameToExtract,
                        $extractTo . DIRECTORY_SEPARATOR . $fileNameToExtract
                    );
                }
            } else {
                File::move(
                    $this->extracted_path . DIRECTORY_SEPARATOR . $fileNameToExtract,
                    $extractTo . DIRECTORY_SEPARATOR . $fileNameToExtract
                );
            }
        }
    }

    public function filename()
    {
        return preg_replace(['/\.tar\.gz$/', '/\.zip$/'], '', $this->name);
    }

    public function getTempName(): string
    {
        return match (true) {
            str_contains($this->name, '.zip') => $this->temporary_name . '.zip',
            str_contains($this->name, '.tar.gz') => $this->temporary_name . '.tar.gz',
        };
    }

    public function removeArchive(): void
    {
        File::delete($this->download_path . DIRECTORY_SEPARATOR . $this->getTempName());
    }

    public function getArchiver(): PharData|ZipHandler
    {
        return match (true) {
            str_contains($this->name, '.zip') => new ZipHandler($this->download_path . DIRECTORY_SEPARATOR . $this->getTempName()),
            str_contains($this->name, '.tar.gz') => new PharData($this->download_path . DIRECTORY_SEPARATOR . $this->getTempName()),
        };
    }
}
