<?php

namespace Turso\PHP\Installer\ValueObjects;

class EnvironmentObject
{
    public string $id;
    public string $name;
    public int $token_id;
    public array $variables;
    public string $created_at;
    public string $updated_at;

    public static function fromArray(array $data): self
    {
        $environment = new self;
        $environment->id = $data['id'];
        $environment->name = $data['name'];
        $environment->token_id = $data['token_id'];
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
            'token_id' => $this->token_id,
            'variables' => $this->variables,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
