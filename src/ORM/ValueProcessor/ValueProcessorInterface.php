<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

/**
 * @author Sébastien Muler
 */
interface ValueProcessorInterface
{
    /**
     * @throws \JsonException
     */
    public function process(mixed $value): mixed;

    /**
     * @throws \JsonException
     */
    public function convertToColumnValue(mixed $value, string $type): mixed;

    /**
     * @throws \JsonException
     */
    public function convertToPhpValue(mixed $value, string $type): mixed;

    public function canProcess(mixed $typeInfo): bool;

    public function isValidType(string $type): bool;

    /**
     * @return array<string>
     */
    public function getSupportedTypes(): array;

    public function normalizeType(string $type): string;

    public function getDefaultValue(string $type): mixed;
}
