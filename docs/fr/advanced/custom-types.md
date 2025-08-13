# Types Personnalisés

Guide pour créer et utiliser des types de données personnalisés dans MulerTech Database.

## Table des Matières
- [Système de types](#système-de-types)
- [Création de types personnalisés](#création-de-types-personnalisés)
- [Types composites](#types-composites)
- [Types avec validation](#types-avec-validation)
- [Sérialisation avancée](#sérialisation-avancée)
- [Optimisations et performance](#optimisations-et-performance)

## Système de types

### Architecture des types

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Types;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
abstract class Type
{
    /** @var array<string, string> */
    private static array $typeMap = [];
    
    /** @var array<string, Type> */
    private static array $typesMap = [];

    /**
     * @param string $name
     * @param string $className
     * @return void
     */
    public static function addType(string $name, string $className): void
    {
        if (isset(self::$typeMap[$name])) {
            throw new TypeException("Type '{$name}' already exists");
        }

        self::$typeMap[$name] = $className;
    }

    /**
     * @param string $name
     * @return Type
     */
    public static function getType(string $name): Type
    {
        if (!isset(self::$typesMap[$name])) {
            if (!isset(self::$typeMap[$name])) {
                throw new TypeException("Unknown type '{$name}'");
            }

            $className = self::$typeMap[$name];
            self::$typesMap[$name] = new $className();
        }

        return self::$typesMap[$name];
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function hasType(string $name): bool
    {
        return isset(self::$typeMap[$name]);
    }

    /**
     * @param string $name
     * @param string $className
     * @return void
     */
    public static function overrideType(string $name, string $className): void
    {
        self::$typeMap[$name] = $className;
        unset(self::$typesMap[$name]);
    }

    // Méthodes abstraites à implémenter
    abstract public function convertToDatabaseValue(mixed $value, Platform $platform): mixed;
    
    abstract public function convertToPHPValue(mixed $value, Platform $platform): mixed;
    
    abstract public function getSQLDeclaration(array $column, Platform $platform): string;
    
    abstract public function getName(): string;

    // Méthodes optionnelles
    public function getDefaultLength(Platform $platform): ?int
    {
        return null;
    }

    public function requiresSQLCommentHint(Platform $platform): bool
    {
        return false;
    }

    public function canRequireSQLConversion(): bool
    {
        return false;
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, Platform $platform): string
    {
        return $sqlExpr;
    }

    public function convertToPHPValueSQL(string $sqlExpr, Platform $platform): string
    {
        return $sqlExpr;
    }
}
```

### Interface de base pour types personnalisés

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Types;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface CustomTypeInterface
{
    public function getName(): string;
    
    public function getSQLDeclaration(array $column, Platform $platform): string;
    
    public function convertToDatabaseValue(mixed $value, Platform $platform): mixed;
    
    public function convertToPHPValue(mixed $value, Platform $platform): mixed;
    
    public function getDefaultOptions(): array;
    
    public function validate(mixed $value): bool;
    
    public function sanitize(mixed $value): mixed;
}
```

## Création de types personnalisés

### Type Email avec validation

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Types\Type;
use MulerTech\Database\Types\CustomTypeInterface;
use MulerTech\Database\Platform\Platform;
use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EmailType extends Type implements CustomTypeInterface
{
    public const EMAIL = 'email';

    public function getName(): string
    {
        return self::EMAIL;
    }

    public function getSQLDeclaration(array $column, Platform $platform): string
    {
        $length = $column['length'] ?? 255;
        return $platform->getVarcharTypeDeclarationSQL(['length' => $length]);
    }

    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$this->validate($value)) {
            throw new InvalidArgumentException("Invalid email format: {$value}");
        }

        return strtolower(trim($value));
    }

    public function convertToPHPValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    public function getDefaultOptions(): array
    {
        return [
            'length' => 255,
            'allow_plus_addressing' => true,
            'allow_international' => true,
        ];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function sanitize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtolower(trim($value));
    }

    public function requiresSQLCommentHint(Platform $platform): bool
    {
        return true;
    }
}
```

### Type JSON personnalisé

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Types\Type;
use MulerTech\Database\Types\CustomTypeInterface;
use MulerTech\Database\Platform\Platform;
use JsonException;
use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class JsonType extends Type implements CustomTypeInterface
{
    public const JSON = 'json';

    public function getName(): string
    {
        return self::JSON;
    }

    public function getSQLDeclaration(array $column, Platform $platform): string
    {
        if ($platform->hasNativeJsonType()) {
            return 'JSON';
        }

        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Could not encode value to JSON: {$e->getMessage()}", 0, $e);
        }
    }

    public function convertToPHPValue(mixed $value, Platform $platform): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Could not decode JSON: {$e->getMessage()}", 0, $e);
        }
    }

    public function getDefaultOptions(): array
    {
        return [
            'max_depth' => 512,
            'strict_validation' => true,
        ];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        try {
            json_encode($value, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException) {
            return false;
        }
    }

    public function sanitize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Nettoyer récursivement les valeurs
        return $this->sanitizeRecursive($value);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeRecursive(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeRecursive'], $value);
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    public function requiresSQLCommentHint(Platform $platform): bool
    {
        return !$platform->hasNativeJsonType();
    }
}
```

### Type pour les énumérations

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Types\Type;
use MulerTech\Database\Types\CustomTypeInterface;
use MulerTech\Database\Platform\Platform;
use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
abstract class EnumType extends Type implements CustomTypeInterface
{
    abstract protected function getValues(): array;
    
    abstract protected function getEnumClass(): string;

    public function getSQLDeclaration(array $column, Platform $platform): string
    {
        $values = array_map(
            fn($value) => $platform->quoteStringLiteral($value),
            $this->getValues()
        );

        if ($platform->supportsEnums()) {
            return sprintf('ENUM(%s)', implode(', ', $values));
        }

        return $platform->getVarcharTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        // Support pour les objets enum PHP 8.1+
        if (is_object($value) && enum_exists($this->getEnumClass())) {
            $value = $value->value;
        }

        if (!$this->validate($value)) {
            $validValues = implode(', ', $this->getValues());
            throw new InvalidArgumentException(
                "Invalid enum value '{$value}'. Valid values are: {$validValues}"
            );
        }

        return $value;
    }

    public function convertToPHPValue(mixed $value, Platform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        // Convertir en objet enum si disponible
        $enumClass = $this->getEnumClass();
        if (enum_exists($enumClass)) {
            return $enumClass::from($value);
        }

        return $value;
    }

    public function getDefaultOptions(): array
    {
        return [
            'values' => $this->getValues(),
            'strict_validation' => true,
        ];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        // Support pour les objets enum
        if (is_object($value) && enum_exists($this->getEnumClass())) {
            $value = $value->value;
        }

        return in_array($value, $this->getValues(), true);
    }

    public function sanitize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value;
    }
}

// Exemple d'implémentation concrète
class UserStatusType extends EnumType
{
    public const USER_STATUS = 'user_status';

    public function getName(): string
    {
        return self::USER_STATUS;
    }

    protected function getValues(): array
    {
        return ['active', 'inactive', 'pending', 'suspended', 'deleted'];
    }

    protected function getEnumClass(): string
    {
        return \App\Enum\UserStatus::class;
    }
}
```

## Types composites

### Type pour les adresses

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Types\Type;
use MulerTech\Database\Types\CustomTypeInterface;
use MulerTech\Database\Platform\Platform;
use App\ValueObject\Address;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class AddressType extends Type implements CustomTypeInterface
{
    public const ADDRESS = 'address';

    public function getName(): string
    {
        return self::ADDRESS;
    }

    public function getSQLDeclaration(array $column, Platform $platform): string
    {
        // Stocker comme JSON
        if ($platform->hasNativeJsonType()) {
            return 'JSON';
        }

        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Address) {
            $data = [
                'street' => $value->getStreet(),
                'city' => $value->getCity(),
                'postal_code' => $value->getPostalCode(),
                'country' => $value->getCountry(),
                'coordinates' => [
                    'latitude' => $value->getLatitude(),
                    'longitude' => $value->getLongitude(),
                ],
            ];

            return json_encode($data, JSON_THROW_ON_ERROR);
        }

        throw new InvalidArgumentException('Expected Address object, got ' . gettype($value));
    }

    public function convertToPHPValue(mixed $value, Platform $platform): ?Address
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return new Address(
            $data['street'] ?? '',
            $data['city'] ?? '',
            $data['postal_code'] ?? '',
            $data['country'] ?? '',
            $data['coordinates']['latitude'] ?? null,
            $data['coordinates']['longitude'] ?? null
        );
    }

    public function getDefaultOptions(): array
    {
        return [
            'validate_coordinates' => true,
            'require_country' => true,
        ];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!$value instanceof Address) {
            return false;
        }

        // Validation basique
        return !empty($value->getStreet()) && !empty($value->getCity());
    }

    public function sanitize(mixed $value): ?Address
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Address) {
            return $value;
        }

        // Tentative de conversion depuis un array
        if (is_array($value)) {
            return new Address(
                trim($value['street'] ?? ''),
                trim($value['city'] ?? ''),
                trim($value['postal_code'] ?? ''),
                trim($value['country'] ?? ''),
                $value['latitude'] ?? null,
                $value['longitude'] ?? null
            );
        }

        return null;
    }

    public function requiresSQLCommentHint(Platform $platform): bool
    {
        return !$platform->hasNativeJsonType();
    }
}
```

### Type pour les plages de dates

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Types\Type;
use MulerTech\Database\Types\CustomTypeInterface;
use MulerTech\Database\Platform\Platform;
use App\ValueObject\DateRange;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DateRangeType extends Type implements CustomTypeInterface
{
    public const DATE_RANGE = 'date_range';

    public function getName(): string
    {
        return self::DATE_RANGE;
    }

    public function getSQLDeclaration(array $column, Platform $platform): string
    {
        // Stocker comme chaîne au format "YYYY-MM-DD,YYYY-MM-DD"
        return $platform->getVarcharTypeDeclarationSQL(['length' => 21]);
    }

    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateRange) {
            $start = $value->getStartDate()->format('Y-m-d');
            $end = $value->getEndDate()->format('Y-m-d');
            return "{$start},{$end}";
        }

        throw new InvalidArgumentException('Expected DateRange object, got ' . gettype($value));
    }

    public function convertToPHPValue(mixed $value, Platform $platform): ?DateRange
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = explode(',', $value);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Invalid date range format: {$value}");
        }

        $startDate = DateTime::createFromFormat('Y-m-d', $parts[0]);
        $endDate = DateTime::createFromFormat('Y-m-d', $parts[1]);

        if (!$startDate || !$endDate) {
            throw new InvalidArgumentException("Invalid date format in range: {$value}");
        }

        return new DateRange($startDate, $endDate);
    }

    public function getDefaultOptions(): array
    {
        return [
            'validate_order' => true, // Vérifier que start <= end
            'allow_same_date' => true,
        ];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!$value instanceof DateRange) {
            return false;
        }

        // Vérifier que la date de début <= date de fin
        return $value->getStartDate() <= $value->getEndDate();
    }

    public function sanitize(mixed $value): ?DateRange
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateRange) {
            return $value;
        }

        // Tentative de conversion depuis un array
        if (is_array($value) && isset($value['start'], $value['end'])) {
            $start = $value['start'] instanceof DateTimeInterface 
                ? $value['start'] 
                : new DateTime($value['start']);
            
            $end = $value['end'] instanceof DateTimeInterface 
                ? $value['end'] 
                : new DateTime($value['end']);

            return new DateRange($start, $end);
        }

        return null;
    }
}
```

## Types avec validation

### Type pour les numéros de téléphone

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Types\Type;
use MulerTech\Database\Types\CustomTypeInterface;
use MulerTech\Database\Platform\Platform;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumber;
use libphonenumber\NumberParseException;
use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PhoneNumberType extends Type implements CustomTypeInterface
{
    public const PHONE_NUMBER = 'phone_number';

    private PhoneNumberUtil $phoneUtil;
    private string $defaultRegion;

    public function __construct(string $defaultRegion = 'FR')
    {
        $this->phoneUtil = PhoneNumberUtil::getInstance();
        $this->defaultRegion = $defaultRegion;
    }

    public function getName(): string
    {
        return self::PHONE_NUMBER;
    }

    public function getSQLDeclaration(array $column, Platform $platform): string
    {
        // Format international : +33123456789
        return $platform->getVarcharTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            return $this->phoneUtil->format($value, \libphonenumber\PhoneNumberFormat::E164);
        }

        if (is_string($value)) {
            try {
                $phoneNumber = $this->phoneUtil->parse($value, $this->defaultRegion);
                if ($this->phoneUtil->isValidNumber($phoneNumber)) {
                    return $this->phoneUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::E164);
                }
            } catch (NumberParseException $e) {
                throw new InvalidArgumentException("Invalid phone number: {$value}");
            }
        }

        throw new InvalidArgumentException('Expected PhoneNumber object or string, got ' . gettype($value));
    }

    public function convertToPHPValue(mixed $value, Platform $platform): ?PhoneNumber
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $this->phoneUtil->parse($value, null);
        } catch (NumberParseException $e) {
            throw new InvalidArgumentException("Could not parse phone number: {$value}");
        }
    }

    public function getDefaultOptions(): array
    {
        return [
            'default_region' => $this->defaultRegion,
            'strict_validation' => true,
            'format' => 'E164',
        ];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if ($value instanceof PhoneNumber) {
            return $this->phoneUtil->isValidNumber($value);
        }

        if (is_string($value)) {
            try {
                $phoneNumber = $this->phoneUtil->parse($value, $this->defaultRegion);
                return $this->phoneUtil->isValidNumber($phoneNumber);
            } catch (NumberParseException) {
                return false;
            }
        }

        return false;
    }

    public function sanitize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            return $value;
        }

        if (is_string($value)) {
            // Nettoyer la chaîne
            $cleaned = preg_replace('/[^\d\+\-\s\(\)]/', '', $value);
            $cleaned = trim($cleaned);

            try {
                return $this->phoneUtil->parse($cleaned, $this->defaultRegion);
            } catch (NumberParseException) {
                return null;
            }
        }

        return null;
    }
}
```

### Type pour les mots de passe hashés

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Types\Type;
use MulerTech\Database\Types\CustomTypeInterface;
use MulerTech\Database\Platform\Platform;
use App\ValueObject\HashedPassword;
use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class HashedPasswordType extends Type implements CustomTypeInterface
{
    public const HASHED_PASSWORD = 'hashed_password';

    private int $cost;
    private string|int $algorithm;

    public function __construct(int $cost = 12, string|int $algorithm = PASSWORD_DEFAULT)
    {
        $this->cost = $cost;
        $this->algorithm = $algorithm;
    }

    public function getName(): string
    {
        return self::HASHED_PASSWORD;
    }

    public function getSQLDeclaration(array $column, Platform $platform): string
    {
        // Hash bcrypt fait généralement 60 caractères
        return $platform->getVarcharTypeDeclarationSQL(['length' => 255]);
    }

    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof HashedPassword) {
            return $value->getHash();
        }

        if (is_string($value)) {
            // Si c'est déjà un hash, le retourner tel quel
            if ($this->isHash($value)) {
                return $value;
            }

            // Sinon, hasher le mot de passe en clair
            return password_hash($value, $this->algorithm, ['cost' => $this->cost]);
        }

        throw new InvalidArgumentException('Expected HashedPassword object or string, got ' . gettype($value));
    }

    public function convertToPHPValue(mixed $value, Platform $platform): ?HashedPassword
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new HashedPassword($value);
    }

    public function getDefaultOptions(): array
    {
        return [
            'cost' => $this->cost,
            'algorithm' => $this->algorithm,
            'auto_hash' => true,
        ];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if ($value instanceof HashedPassword) {
            return $this->isValidHash($value->getHash());
        }

        if (is_string($value)) {
            // Accepter les mots de passe en clair ou les hashs
            return strlen($value) >= 1;
        }

        return false;
    }

    public function sanitize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof HashedPassword) {
            return $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return null;
    }

    /**
     * @param string $value
     * @return bool
     */
    private function isHash(string $value): bool
    {
        // Vérifier si la chaîne ressemble à un hash bcrypt
        return preg_match('/^\$2[ayx]\$\d{2}\$/', $value) === 1;
    }

    /**
     * @param string $hash
     * @return bool
     */
    private function isValidHash(string $hash): bool
    {
        $info = password_get_info($hash);
        return $info['algo'] !== null && $info['algo'] !== 0;
    }
}
```

## Sérialisation avancée

### Type pour les objets sérialisés

```php
<?php

declare(strict_types=1);

namespace App\Types;

use MulerTech\Database\Types\Type;
use MulerTech\Database\Types\CustomTypeInterface;
use MulerTech\Database\Platform\Platform;
use InvalidArgumentException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SerializedObjectType extends Type implements CustomTypeInterface
{
    public const SERIALIZED_OBJECT = 'serialized_object';

    /** @var array<string> */
    private array $allowedClasses;
    private string $serializationMethod;

    /**
     * @param array<string> $allowedClasses
     * @param string $serializationMethod
     */
    public function __construct(array $allowedClasses = [], string $serializationMethod = 'json')
    {
        $this->allowedClasses = $allowedClasses;
        $this->serializationMethod = $serializationMethod;
    }

    public function getName(): string
    {
        return self::SERIALIZED_OBJECT;
    }

    public function getSQLDeclaration(array $column, Platform $platform): string
    {
        if ($platform->hasNativeJsonType() && $this->serializationMethod === 'json') {
            return 'JSON';
        }

        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!empty($this->allowedClasses) && !in_array(get_class($value), $this->allowedClasses)) {
            throw new InvalidArgumentException(
                'Class ' . get_class($value) . ' is not allowed for serialization'
            );
        }

        return match ($this->serializationMethod) {
            'json' => $this->serializeAsJson($value),
            'php' => $this->serializeAsPhp($value),
            'msgpack' => $this->serializeAsMsgpack($value),
            default => throw new InvalidArgumentException("Unknown serialization method: {$this->serializationMethod}")
        };
    }

    public function convertToPHPValue(mixed $value, Platform $platform): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return match ($this->serializationMethod) {
            'json' => $this->unserializeFromJson($value),
            'php' => $this->unserializeFromPhp($value),
            'msgpack' => $this->unserializeFromMsgpack($value),
            default => throw new InvalidArgumentException("Unknown serialization method: {$this->serializationMethod}")
        };
    }

    public function getDefaultOptions(): array
    {
        return [
            'allowed_classes' => $this->allowedClasses,
            'serialization_method' => $this->serializationMethod,
            'compression' => false,
        ];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!empty($this->allowedClasses) && !in_array(get_class($value), $this->allowedClasses)) {
            return false;
        }

        try {
            $this->convertToDatabaseValue($value, new \MulerTech\Database\Platform\MySQLPlatform());
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function sanitize(mixed $value): mixed
    {
        return $value; // Les objets ne nécessitent généralement pas de nettoyage
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function serializeAsJson(mixed $value): string
    {
        $data = [
            'class' => get_class($value),
            'data' => $this->extractObjectData($value)
        ];

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $value
     * @return object
     */
    private function unserializeFromJson(string $value): object
    {
        $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        
        if (!isset($data['class'], $data['data'])) {
            throw new InvalidArgumentException('Invalid serialized object format');
        }

        return $this->reconstructObject($data['class'], $data['data']);
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function serializeAsPhp(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * @param string $value
     * @return object
     */
    private function unserializeFromPhp(string $value): object
    {
        $object = unserialize($value, [
            'allowed_classes' => empty($this->allowedClasses) ? true : $this->allowedClasses
        ]);

        if (!is_object($object)) {
            throw new InvalidArgumentException('Unserialized value is not an object');
        }

        return $object;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function serializeAsMsgpack(mixed $value): string
    {
        if (!extension_loaded('msgpack')) {
            throw new InvalidArgumentException('msgpack extension is required');
        }

        $data = [
            'class' => get_class($value),
            'data' => $this->extractObjectData($value)
        ];

        return msgpack_pack($data);
    }

    /**
     * @param string $value
     * @return object
     */
    private function unserializeFromMsgpack(string $value): object
    {
        if (!extension_loaded('msgpack')) {
            throw new InvalidArgumentException('msgpack extension is required');
        }

        $data = msgpack_unpack($value);
        
        if (!isset($data['class'], $data['data'])) {
            throw new InvalidArgumentException('Invalid serialized object format');
        }

        return $this->reconstructObject($data['class'], $data['data']);
    }

    /**
     * @param object $object
     * @return array<string, mixed>
     */
    private function extractObjectData(object $object): array
    {
        // Utiliser la réflexion pour extraire les propriétés
        $reflection = new \ReflectionClass($object);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $data[$property->getName()] = $property->getValue($object);
        }

        return $data;
    }

    /**
     * @param string $className
     * @param array<string, mixed> $data
     * @return object
     */
    private function reconstructObject(string $className, array $data): object
    {
        $reflection = new \ReflectionClass($className);
        $object = $reflection->newInstanceWithoutConstructor();

        foreach ($data as $propertyName => $value) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue($object, $value);
            }
        }

        return $object;
    }
}
```

## Optimisations et performance

### Enregistrement des types personnalisés

```php
<?php

declare(strict_types=1);

namespace App\Bootstrap;

use MulerTech\Database\Types\Type;
use App\Types\EmailType;
use App\Types\JsonType;
use App\Types\UserStatusType;
use App\Types\AddressType;
use App\Types\DateRangeType;
use App\Types\PhoneNumberType;
use App\Types\HashedPasswordType;
use App\Types\SerializedObjectType;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TypeRegistration
{
    public static function registerCustomTypes(): void
    {
        // Types simples
        Type::addType(EmailType::EMAIL, EmailType::class);
        Type::addType(JsonType::JSON, JsonType::class);
        Type::addType(UserStatusType::USER_STATUS, UserStatusType::class);
        
        // Types composites
        Type::addType(AddressType::ADDRESS, AddressType::class);
        Type::addType(DateRangeType::DATE_RANGE, DateRangeType::class);
        
        // Types avec validation
        Type::addType(PhoneNumberType::PHONE_NUMBER, PhoneNumberType::class);
        Type::addType(HashedPasswordType::HASHED_PASSWORD, HashedPasswordType::class);
        
        // Types avancés
        Type::addType(SerializedObjectType::SERIALIZED_OBJECT, SerializedObjectType::class);
    }

    /**
     * @return array<string, string>
     */
    public static function getTypeMapping(): array
    {
        return [
            'email' => EmailType::class,
            'json' => JsonType::class,
            'user_status' => UserStatusType::class,
            'address' => AddressType::class,
            'date_range' => DateRangeType::class,
            'phone_number' => PhoneNumberType::class,
            'hashed_password' => HashedPasswordType::class,
            'serialized_object' => SerializedObjectType::class,
        ];
    }
}
```

### Utilisation dans les entités

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use MulerTech\Database\Mapping\Attributes as ORM;
use App\ValueObject\Address;
use App\ValueObject\DateRange;
use App\Enum\UserStatus;
use libphonenumber\PhoneNumber;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[ORM\MtEntity(table: 'users')]
class User
{
    #[ORM\MtColumn(type: 'integer', primaryKey: true, autoIncrement: true)]
    private ?int $id = null;

    #[ORM\MtColumn(type: 'string', length: 100)]
    private string $name;

    #[ORM\MtColumn(type: 'email', unique: true)]
    private string $email;

    #[ORM\MtColumn(type: 'hashed_password')]
    private string $password;

    #[ORM\MtColumn(type: 'user_status')]
    private UserStatus $status;

    #[ORM\MtColumn(type: 'phone_number', nullable: true)]
    private ?PhoneNumber $phoneNumber = null;

    #[ORM\MtColumn(type: 'address', nullable: true)]
    private ?Address $address = null;

    #[ORM\MtColumn(type: 'json', nullable: true)]
    private ?array $preferences = null;

    #[ORM\MtColumn(type: 'date_range', nullable: true)]
    private ?DateRange $subscriptionPeriod = null;

    // Getters et setters...
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): void
    {
        $this->status = $status;
    }

    public function getPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?PhoneNumber $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): void
    {
        $this->address = $address;
    }

    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    public function setPreferences(?array $preferences): void
    {
        $this->preferences = $preferences;
    }

    public function getSubscriptionPeriod(): ?DateRange
    {
        return $this->subscriptionPeriod;
    }

    public function setSubscriptionPeriod(?DateRange $subscriptionPeriod): void
    {
        $this->subscriptionPeriod = $subscriptionPeriod;
    }
}
```

---

**Voir aussi :**
- [Étendre l'ORM](extending-orm.md)
- [Système de plugins](plugins.md)
- [Architecture interne](internals.md)
