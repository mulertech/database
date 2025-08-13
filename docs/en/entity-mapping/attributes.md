# Attributes

Complete guide to entity mapping using PHP 8 attributes in MulerTech Database.

## Overview

MulerTech Database uses PHP 8 attributes for clean, declarative entity mapping. Attributes are placed directly on classes and properties to define how they map to database tables and columns.

```php
<?php

declare(strict_types=1);

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};

#[MtEntity(tableName: 'users')]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;
}
```

## Entity Attributes

### #[MtEntity]

Marks a class as a database entity and configures table mapping.

```php
#[MtEntity(
    tableName: 'custom_table_name',
    repositoryClass: CustomRepository::class
)]
class MyEntity
{
    // ...
}
```

#### Parameters

**tableName: string**
- **Required**: The database table name
- **Default**: Class name converted to snake_case

```php
// Table name: 'users'
#[MtEntity(tableName: 'users')]
class User {}

// Table name: 'user_profiles' (auto-generated from class name)
#[MtEntity]
class UserProfile {}
```

**repositoryClass: string**
- **Optional**: Custom repository class
- **Default**: `EntityRepository::class`

```php
#[MtEntity(
    tableName: 'users',
    repositoryClass: UserRepository::class
)]
class User {}
```

## Column Attributes

### #[MtColumn]

Maps a property to a database column with full configuration options.

```php
#[MtColumn(
    columnName: 'email_address',
    columnType: ColumnType::VARCHAR,
    length: 255,
    nullable: false,
    unique: true,
    defaultValue: null,
    columnKey: ColumnKey::UNIQUE,
    extra: ''
)]
private string $email;
```

#### Required Parameters

**columnType: ColumnType**
- The database column type
- See [Types and Columns](types-and-columns.md) for complete reference

```php
#[MtColumn(columnType: ColumnType::VARCHAR)]
private string $name;

#[MtColumn(columnType: ColumnType::INT)]
private int $age;

#[MtColumn(columnType: ColumnType::DATETIME)]
private DateTime $createdAt;
```

#### Optional Parameters

**columnName: string**
- **Default**: Property name converted to snake_case

```php
// Column name: 'first_name'
#[MtColumn(columnType: ColumnType::VARCHAR, columnName: 'first_name')]
private string $firstName;

// Column name: 'last_name' (auto-generated)
#[MtColumn(columnType: ColumnType::VARCHAR)]
private string $lastName;
```

**length: int**
- For VARCHAR, CHAR types
- **Default**: Depends on column type

```php
#[MtColumn(columnType: ColumnType::VARCHAR, length: 50)]
private string $shortText;

#[MtColumn(columnType: ColumnType::VARCHAR, length: 1000)]
private string $longText;
```

**precision: int, scale: int**
- For DECIMAL/NUMERIC types

```php
#[MtColumn(
    columnType: ColumnType::DECIMAL,
    precision: 10,
    scale: 2
)]
private float $price; // DECIMAL(10,2)
```

**nullable: bool**
- **Default**: `true` for nullable types, `false` for non-nullable

```php
#[MtColumn(columnType: ColumnType::VARCHAR, nullable: false)]
private string $requiredField;

#[MtColumn(columnType: ColumnType::VARCHAR, nullable: true)]
private ?string $optionalField;
```

**unique: bool**
- **Default**: `false`

```php
#[MtColumn(columnType: ColumnType::VARCHAR, unique: true)]
private string $email;
```

**defaultValue: mixed**
- Database default value

```php
#[MtColumn(columnType: ColumnType::BOOLEAN, defaultValue: false)]
private bool $active;

#[MtColumn(columnType: ColumnType::VARCHAR, defaultValue: 'pending')]
private string $status;
```

**columnKey: ColumnKey**
- Special column keys (PRIMARY_KEY, UNIQUE, INDEX)

```php
#[MtColumn(
    columnType: ColumnType::INT,
    columnKey: ColumnKey::PRIMARY_KEY,
    extra: 'auto_increment'
)]
private ?int $id;

#[MtColumn(
    columnType: ColumnType::VARCHAR,
    columnKey: ColumnKey::UNIQUE
)]
private string $email;

#[MtColumn(
    columnType: ColumnType::VARCHAR,
    columnKey: ColumnKey::INDEX
)]
private string $category;
```

**extra: string**
- Additional SQL for column definition

```php
#[MtColumn(
    columnType: ColumnType::INT,
    columnKey: ColumnKey::PRIMARY_KEY,
    extra: 'auto_increment'
)]
private ?int $id;

#[MtColumn(
    columnType: ColumnType::TIMESTAMP,
    extra: 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
)]
private DateTime $updatedAt;
```

## Common Patterns

### Primary Key Patterns

**Auto-increment Integer ID**
```php
#[MtColumn(
    columnType: ColumnType::INT,
    columnKey: ColumnKey::PRIMARY_KEY,
    extra: 'auto_increment'
)]
private ?int $id = null;
```

**UUID Primary Key**
```php
#[MtColumn(
    columnType: ColumnType::CHAR,
    length: 36,
    columnKey: ColumnKey::PRIMARY_KEY
)]
private string $id;

public function __construct()
{
    $this->id = Uuid::uuid4()->toString();
}
```

**Composite Primary Key**
```php
#[MtColumn(
    columnType: ColumnType::INT,
    columnKey: ColumnKey::PRIMARY_KEY
)]
private int $userId;

#[MtColumn(
    columnType: ColumnType::INT,
    columnKey: ColumnKey::PRIMARY_KEY
)]
private int $roleId;
```

### Timestamp Patterns

**Created/Updated Timestamps**
```php
#[MtColumn(
    columnType: ColumnType::DATETIME,
    nullable: false
)]
private DateTime $createdAt;

#[MtColumn(
    columnType: ColumnType::DATETIME,
    nullable: true
)]
private ?DateTime $updatedAt = null;

public function __construct()
{
    $this->createdAt = new DateTime();
}
```

**Automatic Timestamps**
```php
#[MtColumn(
    columnType: ColumnType::TIMESTAMP,
    extra: 'DEFAULT CURRENT_TIMESTAMP'
)]
private DateTime $createdAt;

#[MtColumn(
    columnType: ColumnType::TIMESTAMP,
    extra: 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
)]
private DateTime $updatedAt;
```

### String Field Patterns

**Short Text Fields**
```php
#[MtColumn(columnType: ColumnType::VARCHAR, length: 50)]
private string $firstName;

#[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
private string $lastName;

#[MtColumn(columnType: ColumnType::VARCHAR, length: 255, unique: true)]
private string $email;
```

**Long Text Fields**
```php
#[MtColumn(columnType: ColumnType::TEXT)]
private string $description;

#[MtColumn(columnType: ColumnType::LONGTEXT)]
private string $content;
```

**Enumerated Values**
```php
#[MtColumn(
    columnType: ColumnType::ENUM,
    values: ['active', 'inactive', 'pending'],
    defaultValue: 'pending'
)]
private string $status;
```

### Numeric Field Patterns

**Integer Fields**
```php
#[MtColumn(columnType: ColumnType::INT)]
private int $quantity;

#[MtColumn(columnType: ColumnType::BIGINT)]
private int $largeNumber;

#[MtColumn(columnType: ColumnType::SMALLINT)]
private int $smallNumber;
```

**Decimal Fields**
```php
#[MtColumn(
    columnType: ColumnType::DECIMAL,
    precision: 10,
    scale: 2
)]
private float $price;

#[MtColumn(
    columnType: ColumnType::DECIMAL,
    precision: 5,
    scale: 2
)]
private float $percentage;
```

### Boolean Patterns

```php
#[MtColumn(columnType: ColumnType::BOOLEAN, defaultValue: true)]
private bool $active;

#[MtColumn(columnType: ColumnType::BOOLEAN, defaultValue: false)]
private bool $verified;
```

### JSON Patterns

```php
#[MtColumn(columnType: ColumnType::JSON)]
private array $metadata;

#[MtColumn(columnType: ColumnType::JSON, nullable: true)]
private ?array $settings = null;
```

## Complete Entity Examples

### User Entity

```php
#[MtEntity(tableName: 'users')]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 100,
        nullable: false
    )]
    private string $name;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        unique: true,
        nullable: false
    )]
    private string $email;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        nullable: false
    )]
    private string $password;

    #[MtColumn(
        columnType: ColumnType::BOOLEAN,
        defaultValue: true
    )]
    private bool $active = true;

    #[MtColumn(
        columnType: ColumnType::DATETIME,
        nullable: false
    )]
    private DateTime $createdAt;

    #[MtColumn(
        columnType: ColumnType::DATETIME,
        nullable: true
    )]
    private ?DateTime $lastLoginAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters and setters...
}
```

### Product Entity

```php
#[MtEntity(tableName: 'products')]
class Product
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        nullable: false
    )]
    private string $name;

    #[MtColumn(
        columnType: ColumnType::TEXT,
        nullable: true
    )]
    private ?string $description = null;

    #[MtColumn(
        columnType: ColumnType::DECIMAL,
        precision: 10,
        scale: 2,
        nullable: false
    )]
    private float $price;

    #[MtColumn(
        columnType: ColumnType::INT,
        defaultValue: 0
    )]
    private int $stock = 0;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 50,
        columnKey: ColumnKey::INDEX
    )]
    private string $category;

    #[MtColumn(
        columnType: ColumnType::JSON,
        nullable: true
    )]
    private ?array $attributes = null;

    #[MtColumn(
        columnType: ColumnType::BOOLEAN,
        defaultValue: true
    )]
    private bool $active = true;

    // Constructor and methods...
}
```

## Validation and Best Practices

### 1. Property Naming

Use camelCase for properties, snake_case for columns:

```php
// Good
#[MtColumn(columnType: ColumnType::VARCHAR)]
private string $firstName; // Maps to 'first_name' column

// Explicit mapping when needed
#[MtColumn(columnType: ColumnType::VARCHAR, columnName: 'email_addr')]
private string $emailAddress;
```

### 2. Type Safety

Use strict PHP types that match database types:

```php
// Good - strict typing
#[MtColumn(columnType: ColumnType::INT)]
private int $age;

#[MtColumn(columnType: ColumnType::VARCHAR, nullable: true)]
private ?string $middleName = null;

// Avoid - loose typing
#[MtColumn(columnType: ColumnType::INT)]
private $age; // No type hint
```

### 3. Default Values

Set appropriate default values:

```php
// Good - meaningful defaults
#[MtColumn(columnType: ColumnType::BOOLEAN, defaultValue: false)]
private bool $verified = false;

#[MtColumn(columnType: ColumnType::ENUM, values: ['pending', 'active'], defaultValue: 'pending')]
private string $status = 'pending';
```

### 4. Constraints

Use database constraints for data integrity:

```php
// Unique constraints
#[MtColumn(columnType: ColumnType::VARCHAR, unique: true)]
private string $email;

// Not null constraints
#[MtColumn(columnType: ColumnType::VARCHAR, nullable: false)]
private string $name;

// Indexes for performance
#[MtColumn(columnType: ColumnType::VARCHAR, columnKey: ColumnKey::INDEX)]
private string $category;
```

## Next Steps

- [Types and Columns](types-and-columns.md) - Complete column type reference
- [Relationships](relationships.md) - Define entity relationships
- [Entity Manager](../data-access/entity-manager.md) - Work with mapped entities
