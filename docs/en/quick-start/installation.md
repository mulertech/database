# Installation

## System Requirements

- **PHP 8.4+** with the following extensions:
  - PDO with MySQL/PostgreSQL/SQLite driver
  - Reflection
  - JSON
- **Composer** for dependency management

## Installation via Composer

```bash
composer require mulertech/database "^1.0"
```

## Database Configuration

### Basic Configuration

```php
<?php
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Database configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4'
];

// Initialize components
$pdm = new PhpDatabaseManager($config);
$metadataRegistry = new MetadataRegistry();
$entityManager = new EntityManager($pdm, $metadataRegistry);
```

### Environment Variables

Create a `.env` file for environment-specific configuration:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=my_app
DB_USERNAME=user
DB_PASSWORD=password
DB_CHARSET=utf8mb4
```

### Advanced Configuration

```php
$config = [
    'host' => $_ENV['DB_HOST'],
    'port' => (int) $_ENV['DB_PORT'],
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

## Verification

Test your installation with a simple connection:

```php
try {
    $connection = $pdm->getConnection();
    echo "✅ Database connection successful!\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}
```

## Next Steps

- [First Steps](first-steps.md) - Create your first entity
- [Basic Examples](basic-examples.md) - Common use cases
