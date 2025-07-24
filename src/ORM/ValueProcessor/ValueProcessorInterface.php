<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use JsonException;

/**
 * Interface for value processing strategies
 */
interface ValueProcessorInterface
{
    /**
     * Process a value according to its type
     *
     * @param mixed $value
     * @return mixed
     * @throws JsonException
     */
    public function process(mixed $value): mixed;

    /**
     * Check if this processor can handle the given type information
     *
     * @param mixed $typeInfo
     * @return bool
     */
    public function canProcess(mixed $typeInfo): bool;
}
