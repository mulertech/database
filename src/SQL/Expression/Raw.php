<?php

declare(strict_types=1);

namespace MulerTech\Database\SQL\Expression;

/**
 * Raw SQL value wrapper to bypass automatic parameterization
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class Raw
{
    /**
     * @var string
     */
    private string $value;

    /**
     * @param string $value
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * @param string $value
     * @return self
     */
    public static function value(string $value): self
    {
        return new self($value);
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
