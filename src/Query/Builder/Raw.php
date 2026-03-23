<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

/**
 * Raw SQL value wrapper to bypass automatic parameterization.
 *
 * @author Sébastien Muler
 */
class Raw
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function value(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
