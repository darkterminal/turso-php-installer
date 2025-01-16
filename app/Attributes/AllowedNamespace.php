<?php

namespace Turso\PHP\Installer\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AllowedNamespace
{

    public function __construct(public array|string $namespaces)
    {
        if (is_string($namespaces)) {
            $this->namespaces = [$namespaces];
        }
    }

    /**
     * Validate if the given class belongs to one of the allowed namespaces.
     *
     * @param string $class The fully qualified class name
     * @return bool
     */
    public function isClassAllowed(string $class): bool
    {
        foreach ($this->namespaces as $namespace) {
            if ($class === $namespace) {
                return true;
            }
        }

        return false;
    }
}
