<?php

namespace App\ValueObjects;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RuntimeException;
use ZipArchive;

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
        $asset->download_path = sys_get_temp_dir();
        $asset->extracted_path = $asset->download_path.DIRECTORY_SEPARATOR.$asset->filename();
        $asset->temporary_name = Str::random(40);

        return $asset;
    }

    public function download()
    {
        $request = spin(
            message: 'Downloading Turso/libSQL Extension for PHP...',
            callback: fn () => Http::retry(3, 100)
                ->sink($this->download_path.DIRECTORY_SEPARATOR.$this->getTempName())
                ->get($this->download_url)
        );

        if ($request->getStatusCode() != 200) {
            throw new RuntimeException('Unable to download extension:'.$this->name);
        }
    }

    public function extract(string $extractTo, array $only = [])
    {
        $archiver = $this->getArchiver();

        $extractOnlyFiles = collect($only)
            ->map(fn ($file) => $this->filename().DIRECTORY_SEPARATOR.$file)
            ->toArray();

        $archiver->extractTo($this->download_path, $extractOnlyFiles, true);

        foreach ($only as $fileNameToExtract) {
            info(sprintf('  ✅ Extracted %s', $fileNameToExtract));
            File::move(
                $this->extracted_path.DIRECTORY_SEPARATOR.$fileNameToExtract,
                $extractTo.DIRECTORY_SEPARATOR.$fileNameToExtract
            );
        }
    }

    public function filename()
    {
        return preg_replace(['/\.tar\.gz$/', '/\.zip$/'], '', $this->name);
    }

    public function getTempName(): string
    {
        return match (true) {
            str_contains($this->name, '.zip') => $this->temporary_name.'.zip',
            str_contains($this->name, '.tar.gz') => $this->temporary_name.'.tar.gz',
        };
    }

    public function removeArchive(): void
    {
        File::delete($this->download_path.DIRECTORY_SEPARATOR.$this->getTempName());
    }

    public function getArchiver(): PharData|ZipArchive
    {
        return match (true) {
            str_contains($this->name, '.zip') => new ZipArchive($this->download_path.DIRECTORY_SEPARATOR.$this->getTempName()),
            str_contains($this->name, '.tar.gz') => new PharData($this->download_path.DIRECTORY_SEPARATOR.$this->getTempName()),
        };
    }
}
