<?php

namespace Turso\PHP\Installer\Traits\Guards;

use Turso\PHP\Installer\Attributes\AllowedNamespace;

trait RestrictedTrait
{
    public function ensureAllowedNamespace(): void
    {
        $reflection = new \ReflectionClass($this);
        $trait = collect($reflection->getTraits())->each(fn($trait) => $trait->name)->first();
        collect($trait->getAttributes(AllowedNamespace::class))
            ->map(fn($attribute) => $attribute->newInstance())
            ->each(function (AllowedNamespace $allowedNamespace) {
                if (!$allowedNamespace->isClassAllowed(get_class($this))) {
                    throw new \LogicException(
                        "This command can only be used from the following namespaces: " . implode(', ', $allowedNamespace->namespaces)
                    );
                }
            });
    }
}
