# Installation and Configuration

🌍 **Languages:** [🇫🇷 Français](../../fr/quick-start/installation.md) | [🇬🇧 English](installation.md)

---

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Basic Configuration](#basic-configuration)
- [Advanced Configuration](#advanced-configuration)
- [Installation Verification](#installation-verification)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

### 🔧 Technical Environment

- **PHP 8.4+** with the following extensions:
  - `pdo` (required)
  - `pdo_mysql` for MySQL/MariaDB
  - `pdo_pgsql` for PostgreSQL
  - `pdo_sqlite` for SQLite
  - `iconv` (required)
  - `zlib` (required)

- **Composer** 2.0+
- **Database**:
  - MySQL 5.7+ / MariaDB 10.3+
  - PostgreSQL 12+
  - SQLite 3.25+

### ✅ Prerequisites Check

```bash
# Check PHP version
php --version

# Check PDO extensions
php -m | grep pdo

# Check Composer
composer --version
```

---

## Installation
### 🚀 Installation via Composer

#### Method 1: Direct command
```bash
composer require mulertech/database "^1.0"
```

#### Method 2: Add to composer.json
```json
{
    "require": {
        "mulertech/database": "^1.0"
    }
}
```
```bash
composer install
```

### 🔄 Update
```bash
# Update package only
composer update mulertech/database

# Full update
composer update
```

---

## Basic Configuration

### 📊 Minimal Configuration

```php
<?php
require_once 'vendor/autoload.php';

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Database configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_application',
    'username' => 'db_user',
    'password' => 'db_password',
    'charset' => 'utf8mb4',
    'driver' => 'mysql' // mysql, pgsql, sqlite
];

try {
    // Component initialization
    $pdm = new PhpDatabaseManager($config);
    $metadataRegistry = new MetadataRegistry();
    $entityManager = new EntityManager($pdm, $metadataRegistry);
    
    echo "✅ Connection successful!\\n";
    
} catch (Exception $e) {
    echo "❌ Connection error: " . $e->getMessage() . "\\n";
}
```

### 🗄️ Configuration by Database Type

#### MySQL/MariaDB
```php
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'driver' => 'mysql',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
];
```

#### PostgreSQL
```php
$config = [
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'password',
    'driver' => 'pgsql',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
];
```

#### SQLite
```php
$config = [
    'database' => '/path/to/database.sqlite',
    'driver' => 'sqlite',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
];
```

---

## Advanced Configuration

### 🌍 Environment Variables

Create a `.env` file:
```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=my_application
DB_USERNAME=db_user
DB_PASSWORD=db_password
DB_CHARSET=utf8mb4

# Environment
APP_ENV=development
APP_DEBUG=true

# Cache
CACHE_DRIVER=memory
CACHE_TTL=3600
```

PHP configuration with environment variables:
```php
<?php
// Load environment variables (use vlucas/phpdotenv if needed)
$config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? '',
    'username' => $_ENV['DB_USERNAME'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql'
];
```

### 🔧 Configuration with Events Manager

```php
<?php
use MulerTech\EventManager\EventManager;
use MulerTech\Database\Event\PrePersistEvent;

// Setup with event manager
$eventManager = new EventManager();
$entityManager = new EntityManager($pdm, $metadataRegistry, $eventManager);

// Add listeners
$eventManager->addListener(PrePersistEvent::class, function(PrePersistEvent $event) {
    $entity = $event->getEntity();
    
    // Pre-save logic
    if (method_exists($entity, 'setCreatedAt')) {
        $entity->setCreatedAt(new DateTime());
    }
});
```

### 🗂️ Multi-Environment Configuration

```php
<?php
class DatabaseConfig
{
    public static function get(string $environment = 'development'): array
    {
        $configs = [
            'development' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'myapp_dev',
                'username' => 'dev_user',
                'password' => 'dev_pass',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            ],
            
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            ],
            
            'production' => [
                'host' => $_ENV['DB_HOST'],
                'port' => (int)$_ENV['DB_PORT'],
                'database' => $_ENV['DB_DATABASE'],
                'username' => $_ENV['DB_USERNAME'],
                'password' => $_ENV['DB_PASSWORD'],
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT => true
                ]
            ]
        ];
        
        return $configs[$environment] ?? $configs['development'];
    }
}

// Usage
$config = DatabaseConfig::get($_ENV['APP_ENV'] ?? 'development');
$entityManager = new EntityManager(new PhpDatabaseManager($config), new MetadataRegistry());
```

---

## Installation Verification

### 🧪 Simple Connection Test

Create a `test-connection.php` file:

```php
<?php
require_once 'vendor/autoload.php';

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Mapping\MetadataRegistry;

// Configuration (adapt to your environment)
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'test',
    'username' => 'root',
    'password' => '',
    'driver' => 'mysql'
];

try {
    echo "🔄 Testing connection...\\n";
    
    $pdm = new PhpDatabaseManager($config);
    $metadataRegistry = new MetadataRegistry();
    $entityManager = new EntityManager($pdm, $metadataRegistry);
    
    // Simple query test
    $result = $pdm->executeQuery("SELECT 1 as test");
    
    if ($result && $result[0]['test'] === 1) {
        echo "✅ Connection successful!\\n";
        echo "📊 Driver: " . $config['driver'] . "\\n";
        echo "🗄️ Database: " . $config['database'] . "\\n";
    } else {
        echo "❌ Query test failed\\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\\n";
    echo "💡 Check your database configuration\\n";
}
```

```bash
php test-connection.php
```

### 🔍 Test with Simple Entity

```php
<?php
use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn};
use MulerTech\Database\Mapping\Types\{ColumnType, ColumnKey};

#[MtEntity(tableName: 'test_users')]
class TestUser
{
    #[MtColumn(
        columnType: ColumnType::INT,
        columnKey: ColumnKey::PRIMARY_KEY,
        extra: 'auto_increment'
    )]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 100)]
    private string $name;

    // Getters/Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
}

try {
    // ORM test
    $user = new TestUser();
    $user->setName('Test User');
    
    $entityManager->persist($user);
    $entityManager->flush();
    
    echo "✅ Entity created with ID: " . $user->getId() . "\\n";
    
} catch (Exception $e) {
    echo "❌ ORM error: " . $e->getMessage() . "\\n";
}
```

---

## Troubleshooting

### 🐛 Common Errors

#### Missing PDO extension
```
Error: PDO extension not found
```
**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php8.4-pdo php8.4-mysql

# CentOS/RHEL
sudo yum install php-pdo php-mysql

# macOS (Homebrew)
brew install php@8.4
```

#### MySQL connection error
```
SQLSTATE[HY000] [1045] Access denied for user
```
**Solutions:**
1. Check username/password
2. Check user permissions:
```sql
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

#### Charset error
```
SQLSTATE[HY000] [2019] Can't initialize character set
```
**Solution:**
```php
$config['charset'] = 'utf8mb4';
$config['options'][PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
```

### 🔧 Debug and Logs

#### Enable debug mode
```php
$config['options'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

// Query logging (development only)
$pdm->enableQueryLogging();
```

#### Check logs
```php
// After executing queries
$logs = $pdm->getQueryLogs();
foreach ($logs as $log) {
    echo "Query: " . $log['query'] . "\\n";
    echo "Time: " . $log['time'] . "ms\\n";
}
```

### 📊 Performance Check

```php
<?php
// Basic performance test
$start = microtime(true);

for ($i = 0; $i < 100; $i++) {
    $user = new TestUser();
    $user->setName("User $i");
    $entityManager->persist($user);
}

$entityManager->flush();

$time = (microtime(true) - $start) * 1000;
echo "💾 100 entities created in " . round($time, 2) . "ms\\n";
```

---

## ➡️ Next Steps

Once installation is complete:

1. 📖 Check [First Steps](first-steps.md) to create your first entity
2. 🎯 See [Basic Examples](basic-examples.md) for concrete use cases
3. 🏗️ Explore [Architecture](../core-concepts/architecture.md) to understand concepts

---

## 🔗 Useful Links

- 🏠 [Back to README](../../README.md)
- 📖 [Complete Documentation](../README.md)
- 🐛 [Report an Issue](https://github.com/mulertech/database/issues)
- 💬 [Support](mailto:sebastien.muler@mulertech.net)