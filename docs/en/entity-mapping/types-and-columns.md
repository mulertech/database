# Types and Columns

Complete reference for column types and field configuration in MulerTech Database.

## Column Types Overview

MulerTech Database provides a comprehensive type system that maps PHP types to database column types across different database engines.

```php
use MulerTech\Database\Mapping\Types\ColumnType;

// String types
ColumnType::VARCHAR     // Variable-length string
ColumnType::CHAR        // Fixed-length string
ColumnType::TEXT        // Long text
ColumnType::LONGTEXT    // Very long text

// Numeric types
ColumnType::INT         // 32-bit integer
ColumnType::BIGINT      // 64-bit integer
ColumnType::SMALLINT    // 16-bit integer
ColumnType::DECIMAL     // Fixed-point decimal
ColumnType::FLOAT       // Floating-point number
ColumnType::DOUBLE      // Double-precision float

// Date/Time types
ColumnType::DATE        // Date only
ColumnType::TIME        // Time only
ColumnType::DATETIME    // Date and time
ColumnType::TIMESTAMP   // Unix timestamp

// Other types
ColumnType::BOOLEAN     // Boolean value
ColumnType::JSON        // JSON data
ColumnType::BLOB        // Binary data
ColumnType::ENUM        // Enumerated values
```

## String Types

### VARCHAR

Variable-length string with specified maximum length.

```php
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    length: 255,
    nullable: false
)]
private string $name;
```

**Parameters:**
- `length`: Maximum character length (required)
- `nullable`: Allow NULL values
- `defaultValue`: Default string value
- `unique`: Unique constraint
- `columnKey`: Index type

**Use cases:**
- Names, emails, titles
- Short to medium text fields
- Fields with known maximum length

**Database mapping:**
- MySQL: `VARCHAR(255)`
- PostgreSQL: `VARCHAR(255)`
- SQLite: `TEXT`

### CHAR

Fixed-length string, padded with spaces.

```php
#[MtColumn(
    columnType: ColumnType::CHAR,
    length: 36
)]
private string $uuid;
```

**Use cases:**
- UUIDs, fixed-format codes
- Country codes, currency codes
- Fixed-length identifiers

### TEXT

Long text without length limit.

```php
#[MtColumn(columnType: ColumnType::TEXT)]
private string $description;

#[MtColumn(columnType: ColumnType::TEXT, nullable: true)]
private ?string $notes = null;
```

**Use cases:**
- Articles, descriptions
- User-generated content
- Comments, reviews

### LONGTEXT

Very long text for large content.

```php
#[MtColumn(columnType: ColumnType::LONGTEXT)]
private string $articleContent;
```

**Use cases:**
- Full articles, documents
- Large JSON structures
- Serialized data

## Numeric Types

### INT

Standard 32-bit signed integer.

```php
#[MtColumn(columnType: ColumnType::INT)]
private int $quantity;

#[MtColumn(
    columnType: ColumnType::INT,
    columnKey: ColumnKey::PRIMARY_KEY,
    extra: 'auto_increment'
)]
private ?int $id = null;
```

**Range:** -2,147,483,648 to 2,147,483,647

**Use cases:**
- Primary keys with auto-increment
- Counters, quantities
- Foreign key references

### BIGINT

64-bit signed integer for large numbers.

```php
#[MtColumn(columnType: ColumnType::BIGINT)]
private int $largeId;

#[MtColumn(columnType: ColumnType::BIGINT)]
private int $timestamp;
```

**Range:** -9,223,372,036,854,775,808 to 9,223,372,036,854,775,807

**Use cases:**
- Large primary keys
- Unix timestamps
- Large counters

### SMALLINT

16-bit signed integer for small numbers.

```php
#[MtColumn(columnType: ColumnType::SMALLINT)]
private int $age;

#[MtColumn(columnType: ColumnType::SMALLINT)]
private int $priority;
```

**Range:** -32,768 to 32,767

**Use cases:**
- Age, priority levels
- Small counters
- Enum-like values

### DECIMAL

Fixed-point decimal for precise arithmetic.

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
    scale: 4
)]
private float $percentage;
```

**Parameters:**
- `precision`: Total number of digits
- `scale`: Number of digits after decimal point

**Use cases:**
- Money, prices
- Percentages, rates
- Financial calculations

### FLOAT/DOUBLE

Floating-point numbers for approximate values.

```php
#[MtColumn(columnType: ColumnType::FLOAT)]
private float $temperature;

#[MtColumn(columnType: ColumnType::DOUBLE)]
private float $coordinate;
```

**Use cases:**
- Scientific measurements
- Geographical coordinates
- Approximate calculations

## Date and Time Types

### DATE

Date only (year, month, day).

```php
#[MtColumn(columnType: ColumnType::DATE)]
private DateTime $birthDate;

#[MtColumn(columnType: ColumnType::DATE, nullable: true)]
private ?DateTime $expiryDate = null;
```

**Format:** YYYY-MM-DD

**Use cases:**
- Birth dates, due dates
- Event dates without time
- Date ranges

### TIME

Time only (hour, minute, second).

```php
#[MtColumn(columnType: ColumnType::TIME)]
private DateTime $openingTime;

#[MtColumn(columnType: ColumnType::TIME)]
private DateTime $duration;
```

**Format:** HH:MM:SS

**Use cases:**
- Business hours
- Duration, intervals
- Scheduled times

### DATETIME

Complete date and time.

```php
#[MtColumn(columnType: ColumnType::DATETIME)]
private DateTime $createdAt;

#[MtColumn(columnType: ColumnType::DATETIME, nullable: true)]
private ?DateTime $updatedAt = null;

public function __construct()
{
    $this->createdAt = new DateTime();
}
```

**Format:** YYYY-MM-DD HH:MM:SS

**Use cases:**
- Created/updated timestamps
- Event scheduling
- Audit trails

### TIMESTAMP

Unix timestamp with automatic updates.

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

**Use cases:**
- Automatic timestamps
- High-precision timing
- System-managed dates

## Special Types

### BOOLEAN

True/false values.

```php
#[MtColumn(columnType: ColumnType::BOOLEAN, defaultValue: false)]
private bool $active;

#[MtColumn(columnType: ColumnType::BOOLEAN, defaultValue: true)]
private bool $verified;
```

**Database mapping:**
- MySQL: `TINYINT(1)`
- PostgreSQL: `BOOLEAN`
- SQLite: `INTEGER` (0/1)

**Use cases:**
- Flags, switches
- Status indicators
- Permissions

### JSON

Structured JSON data.

```php
#[MtColumn(columnType: ColumnType::JSON)]
private array $metadata;

#[MtColumn(columnType: ColumnType::JSON, nullable: true)]
private ?array $settings = null;

// Usage
$user->setMetadata([
    'preferences' => ['theme' => 'dark'],
    'tags' => ['vip', 'premium']
]);
```

**Use cases:**
- Configuration data
- Flexible attributes
- API responses

### BLOB

Binary large objects.

```php
#[MtColumn(columnType: ColumnType::BLOB)]
private string $imageData;

#[MtColumn(columnType: ColumnType::BLOB, nullable: true)]
private ?string $document = null;
```

**Use cases:**
- File storage
- Binary data
- Encrypted content

### ENUM

Predefined set of values.

```php
#[MtColumn(
    columnType: ColumnType::ENUM,
    values: ['pending', 'approved', 'rejected'],
    defaultValue: 'pending'
)]
private string $status;

#[MtColumn(
    columnType: ColumnType::ENUM,
    values: ['small', 'medium', 'large'],
    defaultValue: 'medium'
)]
private string $size;
```

**Parameters:**
- `values`: Array of allowed values

**Use cases:**
- Status fields
- Categories, types
- Controlled vocabularies

## Column Keys and Constraints

### ColumnKey Enum

```php
use MulerTech\Database\Mapping\Types\ColumnKey;

ColumnKey::PRIMARY_KEY  // Primary key constraint
ColumnKey::UNIQUE       // Unique constraint
ColumnKey::INDEX        // Regular index
ColumnKey::FOREIGN_KEY  // Foreign key (used with relationships)
```

### Primary Keys

```php
// Auto-increment primary key
#[MtColumn(
    columnType: ColumnType::INT,
    columnKey: ColumnKey::PRIMARY_KEY,
    extra: 'auto_increment'
)]
private ?int $id = null;

// UUID primary key
#[MtColumn(
    columnType: ColumnType::CHAR,
    length: 36,
    columnKey: ColumnKey::PRIMARY_KEY
)]
private string $uuid;

// Composite primary key
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

### Unique Constraints

```php
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    length: 255,
    unique: true
)]
private string $email;

// Or using columnKey
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    length: 100,
    columnKey: ColumnKey::UNIQUE
)]
private string $username;
```

### Indexes

```php
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    length: 50,
    columnKey: ColumnKey::INDEX
)]
private string $category;

#[MtColumn(
    columnType: ColumnType::DATETIME,
    columnKey: ColumnKey::INDEX
)]
private DateTime $createdAt;
```

## Type Conversion Examples

### PHP to Database

```php
class TypeExamples
{
    // String conversions
    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name = "John Doe"; // → "John Doe"

    // Numeric conversions
    #[MtColumn(columnType: ColumnType::INT)]
    private int $count = 42; // → 42

    #[MtColumn(columnType: ColumnType::DECIMAL, precision: 8, scale: 2)]
    private float $price = 19.99; // → 19.99

    // Boolean conversions
    #[MtColumn(columnType: ColumnType::BOOLEAN)]
    private bool $active = true; // → 1 (MySQL), TRUE (PostgreSQL)

    // Date conversions
    #[MtColumn(columnType: ColumnType::DATETIME)]
    private DateTime $createdAt; // → "2024-01-15 10:30:00"

    // JSON conversions
    #[MtColumn(columnType: ColumnType::JSON)]
    private array $data = ['key' => 'value']; // → '{"key":"value"}'

    // Null handling
    #[MtColumn(columnType: ColumnType::VARCHAR, nullable: true)]
    private ?string $optional = null; // → NULL
}
```

### Database to PHP

```php
// String from database
$name = $user->getName(); // "John Doe" → string

// Number from database
$count = $user->getCount(); // 42 → int
$price = $product->getPrice(); // 19.99 → float

// Boolean from database
$isActive = $user->isActive(); // 1 → true, 0 → false

// Date from database
$createdAt = $user->getCreatedAt(); // "2024-01-15 10:30:00" → DateTime

// JSON from database
$data = $user->getData(); // '{"key":"value"}' → ['key' => 'value']

// Null from database
$optional = $user->getOptional(); // NULL → null
```

## Best Practices

### 1. Choose Appropriate Types

```php
// Good - appropriate sizes
#[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
private string $email;

#[MtColumn(columnType: ColumnType::SMALLINT)]
private int $age; // 0-150 range

#[MtColumn(columnType: ColumnType::DECIMAL, precision: 10, scale: 2)]
private float $price; // Money values

// Avoid - oversized types
#[MtColumn(columnType: ColumnType::LONGTEXT)]
private string $firstName; // Wasteful for short names
```

### 2. Use Nullable Appropriately

```php
// Required fields
#[MtColumn(columnType: ColumnType::VARCHAR, nullable: false)]
private string $name;

// Optional fields
#[MtColumn(columnType: ColumnType::VARCHAR, nullable: true)]
private ?string $middleName = null;

// Default values for non-nullable fields
#[MtColumn(columnType: ColumnType::BOOLEAN, defaultValue: false)]
private bool $verified = false;
```

### 3. Index Strategy

```php
// Index frequently queried fields
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    columnKey: ColumnKey::INDEX
)]
private string $status;

// Unique constraints for business keys
#[MtColumn(
    columnType: ColumnType::VARCHAR,
    unique: true
)]
private string $email;

// Don't over-index
// Avoid indexing rarely queried or large text fields
```

### 4. Type Safety

```php
// Good - strict typing
#[MtColumn(columnType: ColumnType::INT)]
private int $quantity;

public function setQuantity(int $quantity): void
{
    if ($quantity < 0) {
        throw new InvalidArgumentException('Quantity cannot be negative');
    }
    $this->quantity = $quantity;
}

// Include validation in setters
public function setEmail(string $email): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email format');
    }
    $this->email = $email;
}
```

## Complete Entity Example

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
        columnType: ColumnType::VARCHAR,
        length: 100,
        unique: true,
        nullable: false
    )]
    private string $sku;

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
        columnType: ColumnType::SMALLINT,
        defaultValue: 0
    )]
    private int $stock = 0;

    #[MtColumn(
        columnType: ColumnType::ENUM,
        values: ['draft', 'active', 'discontinued'],
        defaultValue: 'draft'
    )]
    private string $status = 'draft';

    #[MtColumn(
        columnType: ColumnType::JSON,
        nullable: true
    )]
    private ?array $attributes = null;

    #[MtColumn(
        columnType: ColumnType::BOOLEAN,
        defaultValue: true
    )]
    private bool $available = true;

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

    // Getters and setters with validation...
    
    public function setPrice(float $price): void
    {
        if ($price < 0) {
            throw new InvalidArgumentException('Price cannot be negative');
        }
        $this->price = $price;
        $this->updatedAt = new DateTime();
    }

    public function setStock(int $stock): void
    {
        if ($stock < 0) {
            throw new InvalidArgumentException('Stock cannot be negative');
        }
        $this->stock = $stock;
        $this->updatedAt = new DateTime();
    }
}
```

## Next Steps

- [Relationships](relationships.md) - Define entity relationships
- [Entity Manager](../data-access/entity-manager.md) - Work with typed entities
- [Query Builder](../data-access/query-builder.md) - Query with type safety
