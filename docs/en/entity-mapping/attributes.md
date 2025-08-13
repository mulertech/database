# Mapping Attributes

🌍 **Languages:** [🇫🇷 Français](../../fr/entity-mapping/attributes.md) | [🇬🇧 English](attributes.md)

---

## 📋 Table of Contents

- [Overview](#overview)
- [#[MtEntity] - Entity](#mtentity---entity)
- [#[MtColumn] - Column](#mtcolumn---column)
- [#[MtFk] - Foreign Key](#mtfk---foreign-key)
- [#[MtRelations] - Relations](#mtrelations---relations)
- [#[MtIndex] - Index](#mtindex---index)
- [Data Types](#data-types)
- [Advanced Examples](#advanced-examples)
- [Best Practices](#best-practices)

---

## Overview

MulerTech Database uses **PHP 8 attributes** to define the mapping between your classes and the database. This modern approach replaces traditional annotations and configuration files.

### 🎯 Attributes Advantages

- **Type-safe**: Language-level validation
- **IDE-friendly**: Autocompletion and verification
- **Performance**: Native PHP parsing
- **Maintainability**: Everything in source code

### 📦 Required Imports

```php
<?php
use MulerTech\Database\Mapping\Attributes\{
    MtEntity, MtColumn, MtFk, 
    MtOneToOne, MtOneToMany, MtManyToOne, MtManyToMany,
    MtIndex, MtUniqueConstraint
};
use MulerTech\Database\Mapping\Types\{
    ColumnType, ColumnKey, FkRule, IndexType
};
```

---

## #[MtEntity] - Entity

The `#[MtEntity]` attribute marks a class as an entity mapped to a database table.

### 🏷️ Syntax

```php
#[MtEntity(
    tableName: string,              // Table name (required)
    repository?: string,            // Custom repository
    schema?: string,                // Database schema
    readOnly?: bool,                // Read-only entity
    cacheable?: bool,               // Caching enabled
    indexes?: array,                // Table indexes
    uniqueConstraints?: array       // Unique constraints
)]
```

### 📝 Examples

#### Simple Entity
```php
#[MtEntity(tableName: 'users')]
class User
{
    // Properties...
}
```

#### Entity with Custom Repository
```php
#[MtEntity(
    tableName: 'products', 
    repository: ProductRepository::class
)]
class Product
{
    // Properties...
}
```

---

## #[MtColumn] - Column

The `#[MtColumn]` attribute defines the mapping of a property to a database column.

### 🏷️ Syntax

```php
#[MtColumn(
    columnType: ColumnType,         // Column type (required)
    columnName?: string,            // Column name (default: property name)
    length?: int,                   // Length (VARCHAR, etc.)
    precision?: int,                // Precision (DECIMAL)
    scale?: int,                    // Scale (DECIMAL)
    isNullable?: bool,              // Allow null (default: false)
    isUnsigned?: bool,              // Unsigned (numbers)
    columnKey?: ColumnKey,          // Key type
    columnDefault?: string,         // Default value
    extra?: string,                 // Extra SQL (auto_increment, etc.)
    comment?: string                // Column comment
)]
```

### 📊 Column Types

```php
enum ColumnType: string
{
    // Integers
    case TINYINT = 'TINYINT';
    case SMALLINT = 'SMALLINT';
    case MEDIUMINT = 'MEDIUMINT';
    case INT = 'INT';
    case BIGINT = 'BIGINT';
    
    // Decimals
    case DECIMAL = 'DECIMAL';
    case FLOAT = 'FLOAT';
    case DOUBLE = 'DOUBLE';
    
    // Strings
    case CHAR = 'CHAR';
    case VARCHAR = 'VARCHAR';
    case TEXT = 'TEXT';
    case LONGTEXT = 'LONGTEXT';
    
    // Date and time
    case DATE = 'DATE';
    case TIME = 'TIME';
    case DATETIME = 'DATETIME';
    case TIMESTAMP = 'TIMESTAMP';
    
    // Others
    case BOOLEAN = 'BOOLEAN';
    case JSON = 'JSON';
    case ENUM = 'ENUM';
}
```

### 📝 Column Examples

#### Auto-increment Primary Key
```php
#[MtColumn(
    columnType: ColumnType::INT,
    isUnsigned: true,
    isNullable: false,
    columnKey: ColumnKey::PRIMARY_KEY,
    extra: 'auto_increment'
)]
private ?int $id = null;
```

#### Unique Email
```php
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    length: 320,
    isNullable: false,
    columnKey: ColumnKey::UNIQUE,
    comment: 'Unique email address'
)]
private string $email;
```

#### Price with Decimals
```php
#[MtColumn(
    columnType: ColumnType::DECIMAL,
    precision: 10,
    scale: 2,
    isNullable: false,
    isUnsigned: true,
    columnDefault: '0.00'
)]
private float $price;
```

---

## #[MtFk] - Foreign Key

The `#[MtFk]` attribute defines a foreign key constraint.

### 🏷️ Syntax

```php
#[MtFk(
    referencedTable: string,        // Referenced table (required)
    referencedColumn: string,       // Referenced column (required)
    constraintName?: string,        // Constraint name
    onDelete?: FkRule,             // On delete action
    onUpdate?: FkRule              // On update action
)]
```

### 🔄 FK Rules

```php
enum FkRule: string
{
    case RESTRICT = 'RESTRICT';     // Prevent deletion/modification
    case CASCADE = 'CASCADE';       // Delete/modify cascade
    case SET_NULL = 'SET NULL';     // Set to NULL
    case NO_ACTION = 'NO ACTION';   // No action
}
```

### 📝 FK Examples

```php
#[MtColumn(columnType: ColumnType::INT, isUnsigned: true)]
#[MtFk(
    referencedTable: 'users',
    referencedColumn: 'id',
    onDelete: FkRule::CASCADE
)]
private int $userId;
```

---

## #[MtRelations] - Relations

### 🔗 ManyToOne

```php
#[MtManyToOne(
    targetEntity: string,           // Target entity (required)
    joinColumn?: string,            // Join column
    cascade?: array,                // Cascade operations
    fetch?: string                  // Fetch type
)]
```

#### ManyToOne Example
```php
class Post
{
    #[MtColumn(columnType: ColumnType::INT, isUnsigned: true)]
    #[MtFk(referencedTable: 'users', referencedColumn: 'id')]
    private int $authorId;

    #[MtManyToOne(
        targetEntity: User::class,
        joinColumn: 'authorId'
    )]
    private ?User $author = null;
}
```

### 🔗 OneToMany

```php
#[MtOneToMany(
    targetEntity: string,           // Target entity (required)
    mappedBy: string,               // Inverse property (required)
    cascade?: array,                // Cascade operations
    fetch?: string,                 // Fetch type
    orphanRemoval?: bool            // Orphan removal
)]
```

#### OneToMany Example
```php
class Category
{
    #[MtOneToMany(
        targetEntity: Product::class,
        mappedBy: 'categoryId',
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private DatabaseCollection $products;

    public function __construct()
    {
        $this->products = new DatabaseCollection();
    }
}
```

### 🔗 ManyToMany

```php
#[MtManyToMany(
    targetEntity: string,           // Target entity (required)
    joinTable: string,              // Join table (required)
    joinColumn: string,             // Join column (required)
    inverseJoinColumn: string,      // Inverse column (required)
    cascade?: array                 // Cascade operations
)]
```

#### ManyToMany Example
```php
class Post
{
    #[MtManyToMany(
        targetEntity: Tag::class,
        joinTable: 'post_tags',
        joinColumn: 'post_id',
        inverseJoinColumn: 'tag_id'
    )]
    private DatabaseCollection $tags;

    public function __construct()
    {
        $this->tags = new DatabaseCollection();
    }
}
```

---

## Data Types

### 📅 Date Management

```php
class Event
{
    #[MtColumn(columnType: ColumnType::DATE)]
    private DateTime $eventDate;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    #[MtColumn(
        columnType: ColumnType::TIMESTAMP,
        columnDefault: 'CURRENT_TIMESTAMP',
        extra: 'ON UPDATE CURRENT_TIMESTAMP'
    )]
    private DateTime $updatedAt;
}
```

### 🔢 Number Management

```php
class Product
{
    #[MtColumn(columnType: ColumnType::INT, isUnsigned: true)]
    private int $quantity;

    #[MtColumn(columnType: ColumnType::DECIMAL, precision: 10, scale: 2)]
    private float $price;

    #[MtColumn(columnType: ColumnType::FLOAT)]
    private float $discountPercentage;
}
```

### 📝 Text Management

```php
class Article
{
    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $title;

    #[MtColumn(columnType: ColumnType::TEXT)]
    private string $content;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 255,
        columnKey: ColumnKey::UNIQUE
    )]
    private string $slug;
}
```

---

## Advanced Examples

### 👤 Complete User Entity

```php
<?php

namespace App\Entity;

use DateTime;
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtOneToMany};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};
use MulerTech\Database\ORM\DatabaseCollection;

#[MtEntity(
    tableName: 'users',
    repository: UserRepository::class
)]
class User
{
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 100,
        isNullable: false
    )]
    private string $fullName;

    #[MtColumn(
        columnType: ColumnType::VARCHAR,
        length: 320,
        columnKey: ColumnKey::UNIQUE
    )]
    private string $email;

    #[MtColumn(
        columnType: ColumnType::ENUM,
        options: ['active', 'inactive', 'suspended'],
        columnDefault: 'active'
    )]
    private string $status = 'active';

    #[MtColumn(
        columnType: ColumnType::JSON,
        isNullable: true
    )]
    private ?array $preferences = null;

    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt;

    #[MtOneToMany(
        targetEntity: Post::class,
        mappedBy: 'authorId'
    )]
    private DatabaseCollection $posts;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->posts = new DatabaseCollection();
    }

    // Getters and Setters...
}
```

---

## Best Practices

### ✅ Naming Conventions

```php
// ✅ GOOD - Explicit names
#[MtEntity(tableName: 'user_profiles')]
class UserProfile
{
    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $firstName;
}

// ❌ AVOID - Ambiguous names
#[MtEntity(tableName: 'up')]
class UP
{
    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $fn;
}
```

### 🎯 Appropriate Types

```php
class Order
{
    // Auto-increment ID
    #[MtColumn(
        columnType: ColumnType::INT,
        isUnsigned: true,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    // Price with precision
    #[MtColumn(columnType: ColumnType::DECIMAL, precision: 10, scale: 2)]
    private float $totalAmount;

    // Status with enum
    #[MtColumn(
        columnType: ColumnType::ENUM,
        options: ['pending', 'paid', 'cancelled']
    )]
    private string $status;
}
```

---

## ➡️ Next Steps

Explore the following concepts:

1. 🔗 [Entity Relationships](relationships.md) - Complex associations
2. 📊 [Types and Columns](types-and-columns.md) - Advanced data types
3. 🗄️ [Entity Manager](../orm/entity-manager.md) - Entity management
4. 🔧 [Query Builder](../query-builder/basic-queries.md) - Custom queries

---

## 🔗 Useful Links

- 🏠 [Back to README](../../README.md)
- ⬅️ [Basic Examples](../quick-start/basic-examples.md)
- ➡️ [Entity Relationships](relationships.md)
- 📖 [Complete Documentation](../README.md)