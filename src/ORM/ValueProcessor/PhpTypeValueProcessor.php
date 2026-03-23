<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

/**
 * Processes values based on PHP types.
 *
 * @author Sébastien Muler
 */
readonly class PhpTypeValueProcessor implements ValueProcessorInterface
{
    public function __construct(
        private ?string $className = null,
        private ?\Closure $hydrateCallback = null,
    ) {
    }

    public function canProcess(mixed $typeInfo): bool
    {
        if (!is_string($typeInfo)) {
            return false;
        }

        // Check if it's a supported PHP type
        if (in_array($typeInfo, $this->getSupportedTypes(), true)) {
            return true;
        }

        // Check if it's an existing class or interface
        return class_exists($typeInfo) || interface_exists($typeInfo);
    }

    /**
     * @throws \JsonException|\DateMalformedStringException
     */
    public function process(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        if (null === $this->className) {
            return $this->processBasicPhpType($value);
        }

        return match ($this->className) {
            'string' => $this->processString($value),
            'int', 'integer' => $this->processInt($value),
            'float', 'double' => $this->processFloat($value),
            'bool', 'boolean' => $this->processBool($value),
            'array' => $this->processArray($value),
            'object' => $this->processObject($value),
            \DateTime::class => $this->processDateTime($value),
            \DateTimeImmutable::class => $this->processDateTimeImmutable($value),
            default => $this->processCustomClass($value),
        };
    }

    /**
     * @throws \JsonException
     */
    public function convertToColumnValue(mixed $value, string $type): mixed
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \JsonException
     * @throws \DateMalformedStringException
     */
    public function convertToPhpValue(mixed $value, string $type): mixed
    {
        if (null === $value) {
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
            \DateTime::class, \DateTimeImmutable::class,
        ];
    }

    public function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'integer' => 'int',
            'double' => 'float',
            'boolean' => 'bool',
            'datetime' => \DateTime::class,
            'stdclass' => 'object',
            default => $type,
        };
    }

    public function getDefaultValue(string $type): mixed
    {
        return match (strtolower($type)) {
            'string' => '',
            'int', 'integer' => 0,
            'float', 'double' => 0.0,
            'bool', 'boolean' => false,
            'array' => [],
            'object' => new \stdClass(),
            'datetime' => new \DateTime(),
            'datetime_immutable' => new \DateTimeImmutable(),
            default => null,
        };
    }

    /**
     * @throws \JsonException
     */
    private function processBasicPhpType(mixed $value): mixed
    {
        return match (gettype($value)) {
            'string' => $this->processString($value),
            'integer' => $this->processInt($value),
            'double' => $this->processFloat($value),
            'boolean' => $this->processBool($value),
            'array' => $this->processArray($value),
            default => $value,
        };
    }

    /**
     * @throws \JsonException
     */
    private function processString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

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

        return is_string($value) ? (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT) : 0;
    }

    private function processFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return is_string($value)
            ? (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
            : 0.0;
    }

    private function processBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return 0 != $value;
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
     * @return array<mixed>
     *
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

                return is_array($decoded) ? $decoded : [$decoded];
            } catch (\JsonException) {
                throw new \InvalidArgumentException('Invalid JSON string');
            }
        }

        if (is_object($value)) {
            return (array) $value;
        }

        return [$value];
    }

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
            } catch (\JsonException) {
                return (object) ['value' => $value];
            }
        }

        return (object) ['value' => $value];
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function processDateTime(mixed $value): \DateTime
    {
        if ($value instanceof \DateTime) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return new \DateTime($value->format('Y-m-d H:i:s'));
        }

        try {
            $dateString = match (true) {
                is_string($value) => $value,
                is_null($value) => 'now',
                is_scalar($value) => (string) $value,
                default => throw new \TypeError('Value cannot be converted to date string'),
            };

            return new \DateTime($dateString);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Invalid date format');
        }
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function processDateTimeImmutable(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('Y-m-d H:i:s'));
        }

        try {
            $dateString = match (true) {
                is_string($value) => $value,
                is_null($value) => 'now',
                is_scalar($value) => (string) $value,
                default => throw new \TypeError('Value cannot be converted to date string'),
            };

            return new \DateTimeImmutable($dateString);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Invalid date format');
        }
    }

    private function processCustomClass(mixed $value): object
    {
        if (null === $this->className) {
            return new \stdClass();
        }

        if ($value instanceof $this->className) {
            return $value;
        }

        if (is_array($value) && null !== $this->hydrateCallback) {
            return ($this->hydrateCallback)($value, $this->className);
        }

        // Try to create new instance
        try {
            return new $this->className();
        } catch (\Exception) {
            return new \stdClass();
        }
    }
}
