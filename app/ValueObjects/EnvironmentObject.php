<?php

namespace App\ValueObjects;

class EnvironmentObject
{
    public string $id;
    public string $name;
    public array $variables;
    public string $created_at;
    public string $updated_at;

    public static function fromArray(array $data): self
    {
        $environment = new self;
        $environment->id = $data['id'];
        $environment->name = $data['name'];
        $environment->variables = $data['variables'];
        $environment->created_at = $data['created_at'];
        $environment->updated_at = $data['updated_at'];

        return $environment;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'variables' => $this->variables,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
