<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use Closure;
use DateTime;
use Exception;
use TypeError;

/**
 * Processes values based on PHP types
 */
readonly class PhpTypeValueProcessor implements ValueProcessorInterface
{
    public function __construct(
        private string $className,
        private Closure $hydrateCallback
    ) {
    }

    /**
     * @param mixed $typeInfo
     * @return bool
     */
    public function canProcess(mixed $typeInfo): bool
    {
        return is_string($typeInfo) && class_exists($typeInfo);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function process(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->className === DateTime::class || is_subclass_of($this->className, DateTime::class)) {
            return $this->processDateTime($value);
        }

        if (is_array($value)) {
            return $this->processObject($value);
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return DateTime
     */
    private function processDateTime(mixed $value): DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        try {
            $dateString = match (true) {
                is_string($value) => $value,
                is_null($value) => 'now',
                is_scalar($value) => (string) $value,
                default => throw new TypeError('Value cannot be converted to date string')
            };

            return new DateTime($dateString);
        } catch (Exception) {
            return new DateTime();
        }
    }

    /**
     * @param mixed $value
     * @return object
     */
    private function processObject(mixed $value): object
    {
        if (is_array($value)) {
            $arrayData = [];
            foreach ($value as $key => $val) {
                $stringKey = is_string($key) ? $key : (string)$key;
                $arrayData[$stringKey] = $val;
            }

            // Use the hydrator callback to recursively process nested objects
            return ($this->hydrateCallback)($arrayData, $this->className);
        }

        return new $this->className();
    }
}
