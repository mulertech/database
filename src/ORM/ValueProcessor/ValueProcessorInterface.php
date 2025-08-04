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
     * @param mixed $value
     * @return mixed
     * @throws JsonException
     */
    public function process(mixed $value): mixed;

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws JsonException
     */
    public function convertToColumnValue(mixed $value, string $type): mixed;

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws JsonException
     */
    public function convertToPhpValue(mixed $value, string $type): mixed;

    /**
     * @param mixed $typeInfo
     * @return bool
     */
    public function canProcess(mixed $typeInfo): bool;

    /**
     * @param string $type
     * @return bool
     */
    public function isValidType(string $type): bool;

    /**
     * @return array<string>
     */
    public function getSupportedTypes(): array;

    /**
     * @param string $type
     * @return string
     */
    public function normalizeType(string $type): string;

    /**
     * @param string $type
     * @return mixed
     */
    public function getDefaultValue(string $type): mixed;
}
