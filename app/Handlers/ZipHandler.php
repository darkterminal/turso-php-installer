<?php

namespace Turso\PHP\Installer\Handlers;

use Illuminate\Support\Facades\File;

use function Laravel\Prompts\error;

class ZipHandler
{
    protected $zipFilePath;
    protected $zip;

    /**
     * Constructor
     * @param string $filePath Path to the ZIP archive
     * @throws \Exception
     */
    public function __construct($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }

        $this->zipFilePath = $filePath;
        $this->zip = new \ZipArchive();

        // Attempt to open the ZIP archive
        if ($this->zip->open($this->zipFilePath) !== TRUE) {
            throw new \Exception("Failed to open ZIP archive: $filePath");
        }
    }

    /**
     * Make it similar like PharData: Extract the ZIP archive to a destination directory
     * 
     */
    public function extractTo($directory, $files = null, $overwrite = false)
    {
        $fileinfo = pathinfo($this->zipFilePath);
        $outDir = collect([$directory, $fileinfo["filename"]])->implode(DIRECTORY_SEPARATOR);
        $this->zip->extractTo($outDir);
        $this->close();
        $this->moveContentsOneLevelUp($outDir);
        return true;
    }

    public function moveContentsOneLevelUp(string $sourceDir)
    {
        // Ensure the source directory exists
        if (!is_dir($sourceDir)) {
            error("Source directory does not exist: $sourceDir");
            return;
        }

        // Get the parent directory of the source directory
        $parentDir = dirname($sourceDir);

        // Open the source directory
        $sourceDirHandle = opendir($sourceDir);
        if ($sourceDirHandle === false) {
            error("Failed to open source directory: $sourceDir");
            return;
        }

        // Iterate over the contents of the source directory
        while (($file = readdir($sourceDirHandle)) !== false) {
            // Skip '.' and '..'
            if ($file == '.' || $file == '..') {
                continue;
            }

            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $file;
            $destinationPath = $parentDir . DIRECTORY_SEPARATOR . $file;

            // Check if the item is a directory or a file
            if (is_dir($sourcePath)) {
                // If it's a directory, recursively move it
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0755, true); // Create the directory in the parent folder
                }
                // Move the directory contents
                $this->moveContentsOneLevelUp($sourcePath); // Recursively move subdirectories
                rmdir($sourcePath); // Remove the now-empty source directory
            } else {
                // If it's a file, move it
                rename($sourcePath, $destinationPath); // Move the file
            }
        }

        // Close the source directory handle
        closedir($sourceDirHandle);
    }

    /**
     * Close the ZIP archive
     */
    public function close()
    {
        $this->zip->close();
    }
}
