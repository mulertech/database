<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use Closure;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use JsonException;
use TypeError;

/**
 * Processes values based on PHP types
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PhpTypeValueProcessor implements ValueProcessorInterface
{
    /**
     * @param string|null $className
     * @param Closure|null $hydrateCallback
     */
    public function __construct(
        private ?string $className = null,
        private ?Closure $hydrateCallback = null
    ) {
    }

    /**
     * @param mixed $typeInfo
     * @return bool
     */
    public function canProcess(mixed $typeInfo): bool
    {
        return is_string($typeInfo) && (class_exists($typeInfo) || interface_exists($typeInfo));
    }

    /**
     * @param mixed $value
     * @return mixed
     * @throws JsonException
     */
    public function process(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->className === null) {
            return $this->processBasicPhpType($value);
        }

        return match ($this->className) {
            'string' => $this->processString($value),
            'int', 'integer' => $this->processInt($value),
            'float', 'double' => $this->processFloat($value),
            'bool', 'boolean' => $this->processBool($value),
            'array' => $this->processArray($value),
            'object' => $this->processObject($value),
            DateTime::class => $this->processDateTime($value),
            DateTimeImmutable::class => $this->processDateTimeImmutable($value),
            default => $this->processCustomClass($value),
        };
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws JsonException
     */
    public function convertToColumnValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            is_string($value) => $value,
            is_int($value) => $value,
            is_float($value) => $value,
            is_bool($value) => $value ? 1 : 0,
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_object($value) => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws JsonException
     */
    public function convertToPhpValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($type)) {
            'string' => $this->processString($value),
            'int', 'integer' => $this->processInt($value),
            'float', 'double' => $this->processFloat($value),
            'bool', 'boolean' => $this->processBool($value),
            'array' => $this->processArray($value),
            'object' => $this->processObject($value),
            'datetime' => $this->processDateTime($value),
            'datetime_immutable' => $this->processDateTimeImmutable($value),
            default => $value,
        };
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isValidType(string $type): bool
    {
        return in_array($type, $this->getSupportedTypes(), true);
    }

    /**
     * @return array<string>
     */
    public function getSupportedTypes(): array
    {
        return [
            'string', 'int', 'integer', 'float', 'double', 'bool', 'boolean',
            'array', 'object', 'datetime', 'datetime_immutable',
            DateTime::class, DateTimeImmutable::class
        ];
    }

    /**
     * @param string $type
     * @return string
     */
    public function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'integer' => 'int',
            'double' => 'float',
            'boolean' => 'bool',
            'datetime' => DateTime::class,
            'stdclass' => 'object',
            default => $type,
        };
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function getDefaultValue(string $type): mixed
    {
        return match (strtolower($type)) {
            'string' => '',
            'int', 'integer' => 0,
            'float', 'double' => 0.0,
            'bool', 'boolean' => false,
            'array' => [],
            'object' => new \stdClass(),
            'datetime' => new DateTime(),
            'datetime_immutable' => new DateTimeImmutable(),
            default => null,
        };
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function processBasicPhpType(mixed $value): mixed
    {
        return match (gettype($value)) {
            'string' => $this->processString($value),
            'integer' => $this->processInt($value),
            'double' => $this->processFloat($value),
            'boolean' => $this->processBool($value),
            'array' => $this->processArray($value),
            'object' => $value,
            default => $value,
        };
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function processString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return match (true) {
            is_null($value) => '',
            is_scalar($value) => (string) $value,
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    /**
     * @param mixed $value
     * @return int
     */
    private function processInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value)) {
            return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        }

        return 0;
    }

    /**
     * @param mixed $value
     * @return float
     */
    private function processFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }

        return 0.0;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function processBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value != 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     */
    private function processArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (empty($value)) {
                return [];
            }
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Invalid JSON string');
                }
                return is_array($decoded) ? $decoded : [$decoded];
            } catch (JsonException) {
                throw new \InvalidArgumentException('Invalid JSON string');
            }
        }

        if (is_object($value)) {
            return (array) $value;
        }

        return [$value];
    }

    /**
     * @param mixed $value
     * @return object
     */
    private function processObject(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        if (is_array($value)) {
            return (object) $value;
        }

        if (is_string($value)) {
            try {
                $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
                return is_object($decoded) ? $decoded : (object) $decoded;
            } catch (JsonException) {
                return (object) ['value' => $value];
            }
        }

        return (object) ['value' => $value];
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

        if ($value instanceof DateTimeInterface) {
            return new DateTime($value->format('Y-m-d H:i:s'));
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
            throw new \InvalidArgumentException('Invalid date format');
        }
    }

    /**
     * @param mixed $value
     * @return DateTimeImmutable
     */
    private function processDateTimeImmutable(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format('Y-m-d H:i:s'));
        }

        try {
            $dateString = match (true) {
                is_string($value) => $value,
                is_null($value) => 'now',
                is_scalar($value) => (string) $value,
                default => throw new TypeError('Value cannot be converted to date string')
            };

            return new DateTimeImmutable($dateString);
        } catch (Exception) {
            throw new \InvalidArgumentException('Invalid date format');
        }
    }

    /**
     * @param mixed $value
     * @return object
     */
    private function processCustomClass(mixed $value): object
    {
        if ($this->className === null) {
            return new \stdClass();
        }

        if (is_object($value) && $value instanceof $this->className) {
            return $value;
        }

        if (is_array($value) && $this->hydrateCallback !== null) {
            return ($this->hydrateCallback)($value, $this->className);
        }

        // Try to create new instance
        try {
            return new $this->className();
        } catch (Exception) {
            return new \stdClass();
        }
    }
}
