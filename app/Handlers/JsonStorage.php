<?php

namespace Turso\PHP\Installer\Handlers;

use Illuminate\Support\Collection;

class JsonStorage
{
    protected $filePath;

    public function __construct($fileName = 'json_storage.json')
    {
        $this->filePath = $fileName;
        $this->initializeFile();
    }

    /**
     * Initialize the JSON file if it doesn't exist.
     */
    protected function initializeFile()
    {
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([])); // Create an empty JSON array
        }
    }

    /**
     * Load the JSON data as a Laravel Collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function load(): Collection
    {
        $data = file_get_contents($this->filePath);
        return collect(json_decode($data, true));
    }

    /**
     * Save the Laravel Collection back to the JSON file.
     *
     * @param \Illuminate\Support\Collection $collection
     * @return void
     */
    public function save(Collection $collection): void
    {
        file_put_contents($this->filePath, $collection->toJson(JSON_PRETTY_PRINT));
    }

    /**
     * Add a new item to the JSON storage.
     *
     * @param array $item
     * @return void
     */
    public function add(array $item): void
    {
        $collection = $this->load();
        $collection->push($item);
        $this->save($collection);
    }

    /**
     * Update an item in the JSON storage by key.
     *
     * @param string $key
     * @param mixed $value
     * @param array $newData
     * @return void
     */
    public function update(string $key, $value, array $newData): void
    {
        $collection = $this->load();

        $updatedCollection = $collection->map(function ($item) use ($key, $value, $newData) {
            if (isset($item[$key]) && $item[$key] == $value) {
                return array_merge($item, $newData);
            }
            return $item;
        });

        $this->save($updatedCollection);
    }

    /**
     * Delete an item from the JSON storage by key.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function delete(string $key, $value): void
    {
        $collection = $this->load();

        $filteredCollection = $collection->filter(function ($item) use ($key, $value) {
            return !(isset($item[$key]) && $item[$key] == $value);
        });

        $this->save($filteredCollection->values()); // Re-index the array after filtering
    }
}
