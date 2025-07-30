<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use JsonException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface ValueProcessorInterface
{
    /**
     * @param string|int|float|bool|array<mixed>|object|null $value
     * @return string|int|float|bool|array<mixed>|object|null
     * @throws JsonException
     */
    public function process(mixed $value): mixed;

    /**
     * @param object|string|null $typeInfo
     * @return bool
     */
    public function canProcess(mixed $typeInfo): bool;
}
