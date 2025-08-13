# Configuration

🌍 **Languages:** [🇫🇷 Français](../../fr/core-concepts/configuration.md) | [🇬🇧 English](configuration.md)

---

## 📋 Table of Contents

- [Overview](#overview)
- [Basic Configuration](#basic-configuration)
- [Database Connection](#database-connection)
- [Entity Configuration](#entity-configuration)
- [Cache and Performance](#cache-and-performance)
- [Logging and Debug](#logging-and-debug)
- [Connection Pool](#connection-pool)
- [Environments](#environments)
- [Security](#security)
- [Advanced Configuration](#advanced-configuration)

---

## Overview

MulerTech Database configuration allows you to customize all aspects of the ORM behavior, from database connections to performance optimizations.

### 🎯 Configuration Goals

- **Flexibility**: Adapt ORM to different environments
- **Performance**: Optimize performance according to needs
- **Security**: Configure access and permissions
- **Maintenance**: Facilitate debugging and monitoring
- **Extensibility**: Enable advanced customizations

### 🗂️ Configuration Files

```
config/
├── database.php          # Main configuration
├── entities.php          # Entity configuration
├── cache.php            # Cache configuration
└── environments/
    ├── development.php   # Development configuration
    ├── testing.php       # Testing configuration
    └── production.php    # Production configuration
```

---

## Basic Configuration

### 🔧 Minimal Structure

```php
<?php

use MulerTech\Database\Config\Configuration;

$config = new Configuration([
    // === CONNECTION ===
    'host' => 'localhost',
    'database' => 'my_app',
    'username' => 'db_user',
    'password' => 'secure_password',
    'driver' => 'mysql',
    'port' => 3306,
    'charset' => 'utf8mb4',
    
    // === ENTITIES ===
    'entity_paths' => [
        'App\\Entity\\' => __DIR__ . '/src/Entity'
    ],
    
    // === CACHE ===
    'cache_enabled' => true,
    'cache_driver' => 'file',
    'cache_dir' => __DIR__ . '/cache',
    
    // === DEBUG ===
    'debug_mode' => false,
    'log_queries' => false
]);
```

### 📝 Array Configuration

```php
<?php

$databaseConfig = [
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        ]
    ],
    
    'default_connection' => 'default',
    
    'orm' => [
        'auto_generate_proxy_classes' => true,
        'proxy_namespace' => 'App\\Proxy',
        'proxy_dir' => __DIR__ . '/cache/proxies'
    ]
];
```

---

## Database Connection

### 🔌 Supported Drivers

```php
<?php

// MySQL/MariaDB
$mysqlConfig = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myapp',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'engine' => 'InnoDB'
];

// PostgreSQL
$postgresConfig = [
    'driver' => 'postgresql',
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'myapp',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8',
    'schema' => 'public'
];

// SQLite
$sqliteConfig = [
    'driver' => 'sqlite',
    'database' => __DIR__ . '/database.sqlite',
    'foreign_key_constraints' => true
];
```

### 🎛️ Connection Options

```php
<?php

$advancedConfig = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'user',
    'password' => 'password',
    
    // PDO Options
    'options' => [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_TIMEOUT => 30,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
    ],
    
    // SSL Configuration
    'ssl' => [
        'enabled' => true,
        'ca_cert' => '/path/to/ca-cert.pem',
        'client_cert' => '/path/to/client-cert.pem',
        'client_key' => '/path/to/client-key.pem',
        'verify_server_cert' => true
    ],
    
    // Timeouts and limits
    'connect_timeout' => 10,
    'read_timeout' => 30,
    'write_timeout' => 30,
    'max_connections' => 100,
    'idle_timeout' => 300
];
```

### 🔄 Multiple Connections

```php
<?php

$multiDbConfig = [
    'connections' => [
        'primary' => [
            'driver' => 'mysql',
            'host' => 'primary-db.example.com',
            'database' => 'main_app',
            'username' => 'main_user',
            'password' => 'main_password'
        ],
        
        'analytics' => [
            'driver' => 'postgresql',
            'host' => 'analytics-db.example.com',
            'database' => 'analytics',
            'username' => 'analytics_user',
            'password' => 'analytics_password'
        ],
        
        'cache' => [
            'driver' => 'redis',
            'host' => 'redis.example.com',
            'port' => 6379,
            'database' => 0
        ]
    ],
    
    'default_connection' => 'primary'
];

// Usage
$primaryManager = EntityManagerFactory::create('primary');
$analyticsManager = EntityManagerFactory::create('analytics');
```

---

## Entity Configuration

### 🗂️ Entity Discovery

```php
<?php

$entityConfig = [
    'entity_paths' => [
        'App\\Entity\\' => __DIR__ . '/src/Entity',
        'App\\Domain\\User\\' => __DIR__ . '/src/Domain/User/Entity',
        'App\\Domain\\Blog\\' => __DIR__ . '/src/Domain/Blog/Entity'
    ],
    
    // Auto-scan directories
    'auto_scan_directories' => [
        __DIR__ . '/src/Entity',
        __DIR__ . '/src/Domain/*/Entity'
    ],
    
    // Exclusions
    'exclude_paths' => [
        __DIR__ . '/src/Entity/Abstract',
        __DIR__ . '/src/Entity/Traits'
    ],
    
    // Metadata cache
    'metadata_cache_enabled' => true,
    'metadata_cache_driver' => 'file',
    'metadata_cache_dir' => __DIR__ . '/cache/metadata'
];
```

### 🏷️ Attribute Configuration

```php
<?php

use MulerTech\Database\Config\AttributeConfiguration;

$attributeConfig = new AttributeConfiguration([
    // Custom attribute prefixes
    'attribute_prefixes' => [
        'MulerTech\\Database\\Mapping\\Attributes\\',
        'App\\Mapping\\Attributes\\'
    ],
    
    // Strict attribute validation
    'strict_validation' => true,
    
    // Cache parsed attributes
    'cache_parsed_attributes' => true,
    
    // Attribute inheritance
    'inherit_attributes' => true,
    
    // Custom attributes
    'custom_attributes' => [
        'Audit' => App\Mapping\AuditAttribute::class,
        'Versioned' => App\Mapping\VersionedAttribute::class
    ]
]);
```

---

## Cache and Performance

### 💾 Cache Configuration

```php
<?php

$cacheConfig = [
    'cache' => [
        // Main cache
        'default_driver' => 'redis',
        'default_ttl' => 3600,
        
        // Available drivers
        'drivers' => [
            'file' => [
                'class' => FileSystemCache::class,
                'options' => [
                    'cache_dir' => __DIR__ . '/cache/app',
                    'file_extension' => '.cache'
                ]
            ],
            
            'redis' => [
                'class' => RedisCache::class,
                'options' => [
                    'host' => 'localhost',
                    'port' => 6379,
                    'database' => 1,
                    'prefix' => 'mulertech_db:'
                ]
            ],
            
            'memcached' => [
                'class' => MemcachedCache::class,
                'options' => [
                    'servers' => [
                        ['localhost', 11211]
                    ],
                    'prefix' => 'mt_db_'
                ]
            ]
        ],
        
        // Specialized caches
        'result_cache' => [
            'driver' => 'redis',
            'ttl' => 1800,
            'enabled' => true
        ],
        
        'metadata_cache' => [
            'driver' => 'file',
            'ttl' => 86400,
            'enabled' => true
        ],
        
        'query_cache' => [
            'driver' => 'redis',
            'ttl' => 3600,
            'enabled' => true
        ]
    ]
];
```

### ⚡ Performance Optimizations

```php
<?php

$performanceConfig = [
    'performance' => [
        // Query Builder
        'query_builder' => [
            'enable_query_cache' => true,
            'cache_prepared_statements' => true,
            'optimize_joins' => true,
            'batch_size' => 1000
        ],
        
        // EntityManager
        'entity_manager' => [
            'enable_lazy_loading' => true,
            'batch_fetch_size' => 50,
            'enable_second_level_cache' => true,
            'auto_commit' => false
        ],
        
        // Profiling
        'profiling' => [
            'enabled' => false,
            'slow_query_threshold' => 1000, // ms
            'log_slow_queries' => true,
            'track_memory_usage' => true
        ],
        
        // Connection pooling
        'connection_pool' => [
            'enabled' => true,
            'min_connections' => 5,
            'max_connections' => 50,
            'max_idle_time' => 300
        ]
    ]
];
```

---

## Logging and Debug

### 📝 Log Configuration

```php
<?php

use Monolog\Logger;
use Monolog\Handler\FileHandler;
use Monolog\Handler\StreamHandler;

$loggingConfig = [
    'logging' => [
        'enabled' => true,
        'level' => Logger::INFO,
        
        'handlers' => [
            'file' => [
                'class' => FileHandler::class,
                'options' => [
                    'filename' => __DIR__ . '/logs/database.log',
                    'level' => Logger::DEBUG
                ]
            ],
            
            'console' => [
                'class' => StreamHandler::class,
                'options' => [
                    'stream' => 'php://stdout',
                    'level' => Logger::ERROR
                ]
            ]
        ],
        
        'channels' => [
            'query' => [
                'enabled' => true,
                'log_successful_queries' => false,
                'log_failed_queries' => true,
                'log_slow_queries' => true,
                'slow_threshold' => 1000 // ms
            ],
            
            'entity' => [
                'enabled' => true,
                'log_lifecycle_events' => false,
                'log_persistence_operations' => true
            ],
            
            'cache' => [
                'enabled' => true,
                'log_hits' => false,
                'log_misses' => true,
                'log_invalidations' => true
            ]
        ]
    ]
];
```

### 🐛 Debug Mode

```php
<?php

$debugConfig = [
    'debug' => [
        'enabled' => getenv('APP_ENV') === 'development',
        
        'features' => [
            'query_logging' => true,
            'explain_queries' => true,
            'show_generated_sql' => true,
            'track_entity_changes' => true,
            'profile_queries' => true,
            'validate_schema' => true
        ],
        
        'toolbar' => [
            'enabled' => true,
            'show_query_count' => true,
            'show_execution_time' => true,
            'show_memory_usage' => true,
            'show_cache_stats' => true
        ],
        
        'error_handling' => [
            'throw_on_hydration_error' => true,
            'throw_on_validation_error' => true,
            'strict_mode' => true
        ]
    ]
];
```

---

## Connection Pool

### 🏊 Pool Configuration

```php
<?php

$poolConfig = [
    'connection_pool' => [
        'enabled' => true,
        
        'pools' => [
            'read' => [
                'min_connections' => 3,
                'max_connections' => 20,
                'max_idle_time' => 300,
                'connection_timeout' => 10,
                'validation_query' => 'SELECT 1',
                'servers' => [
                    ['host' => 'read-replica-1.example.com', 'weight' => 1],
                    ['host' => 'read-replica-2.example.com', 'weight' => 1],
                    ['host' => 'read-replica-3.example.com', 'weight' => 2]
                ]
            ],
            
            'write' => [
                'min_connections' => 2,
                'max_connections' => 10,
                'max_idle_time' => 600,
                'connection_timeout' => 5,
                'validation_query' => 'SELECT 1',
                'servers' => [
                    ['host' => 'write-master.example.com', 'weight' => 1]
                ]
            ]
        ],
        
        'load_balancing' => [
            'strategy' => 'weighted_round_robin', // round_robin, random, weighted_round_robin
            'health_check_interval' => 30,
            'retry_failed_connections' => true,
            'max_retries' => 3
        ]
    ]
];
```

---

## Environments

### 🌍 Environment Configuration

```php
<?php

// config/environments/development.php
return [
    'debug' => true,
    'cache' => [
        'enabled' => false
    ],
    'logging' => [
        'level' => Logger::DEBUG,
        'channels' => [
            'query' => ['log_successful_queries' => true]
        ]
    ],
    'database' => [
        'host' => 'localhost',
        'database' => 'myapp_dev'
    ]
];

// config/environments/testing.php
return [
    'debug' => true,
    'cache' => [
        'enabled' => false
    ],
    'database' => [
        'driver' => 'sqlite',
        'database' => ':memory:'
    ],
    'fixtures' => [
        'enabled' => true,
        'path' => __DIR__ . '/../fixtures'
    ]
];

// config/environments/production.php
return [
    'debug' => false,
    'cache' => [
        'enabled' => true,
        'default_driver' => 'redis'
    ],
    'logging' => [
        'level' => Logger::ERROR,
        'channels' => [
            'query' => ['log_successful_queries' => false]
        ]
    ],
    'performance' => [
        'connection_pool' => ['enabled' => true],
        'query_cache' => ['enabled' => true]
    ]
];
```

### ⚙️ Environment Loading

```php
<?php

use MulerTech\Database\Config\EnvironmentLoader;

class DatabaseConfigLoader
{
    public static function load(string $environment = null): Configuration
    {
        $environment = $environment ?: getenv('APP_ENV') ?: 'production';
        
        // Base configuration
        $baseConfig = require __DIR__ . '/database.php';
        
        // Environment configuration
        $envConfigFile = __DIR__ . "/environments/{$environment}.php";
        $envConfig = file_exists($envConfigFile) ? require $envConfigFile : [];
        
        // Environment variables
        $envVars = [
            'host' => getenv('DB_HOST'),
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
        ];
        
        // Merge configurations
        $finalConfig = array_merge_recursive(
            $baseConfig,
            $envConfig,
            array_filter($envVars) // Remove null values
        );
        
        return new Configuration($finalConfig);
    }
}
```

---

## Security

### 🔒 Security Configuration

```php
<?php

$securityConfig = [
    'security' => [
        // Encryption
        'encryption' => [
            'enabled' => true,
            'algorithm' => 'AES-256-GCM',
            'key' => getenv('DB_ENCRYPTION_KEY'),
            'rotate_keys' => true,
            'key_rotation_interval' => 86400 * 30 // 30 days
        ],
        
        // Audit
        'audit' => [
            'enabled' => true,
            'log_all_queries' => false,
            'log_sensitive_operations' => true,
            'audit_table' => 'audit_log',
            'retention_period' => 86400 * 365 // 1 year
        ],
        
        // Permissions
        'permissions' => [
            'enforce_row_level_security' => true,
            'default_policy' => 'deny',
            'admin_bypass_enabled' => false
        ],
        
        // Validation
        'validation' => [
            'strict_type_checking' => true,
            'sanitize_inputs' => true,
            'max_query_depth' => 10,
            'max_joins_per_query' => 20
        ]
    ]
];
```

---

## Advanced Configuration

### 🔧 Extensions and Plugins

```php
<?php

$advancedConfig = [
    'extensions' => [
        'enabled' => [
            'audit' => App\Extension\AuditExtension::class,
            'soft_delete' => App\Extension\SoftDeleteExtension::class,
            'timestampable' => App\Extension\TimestampableExtension::class,
            'translatable' => App\Extension\TranslatableExtension::class
        ],
        
        'configuration' => [
            'audit' => [
                'track_all_changes' => true,
                'store_old_values' => true
            ],
            'soft_delete' => [
                'deleted_field' => 'deletedAt',
                'filter_deleted' => true
            ]
        ]
    ],
    
    'custom_types' => [
        'json_document' => App\Type\JsonDocumentType::class,
        'encrypted_string' => App\Type\EncryptedStringType::class,
        'phone_number' => App\Type\PhoneNumberType::class
    ],
    
    'listeners' => [
        'pre_persist' => [
            App\Listener\ValidationListener::class,
            App\Listener\AuditListener::class
        ],
        'post_persist' => [
            App\Listener\NotificationListener::class
        ]
    ],
    
    'middleware' => [
        'query' => [
            App\Middleware\QueryLoggingMiddleware::class,
            App\Middleware\PerformanceMiddleware::class
        ],
        'entity' => [
            App\Middleware\ValidationMiddleware::class,
            App\Middleware\SecurityMiddleware::class
        ]
    ]
];
```

### 🏭 Factory Pattern

```php
<?php

use MulerTech\Database\Config\Configuration;
use MulerTech\Database\ORM\EntityManager;

class EntityManagerFactory
{
    private static array $instances = [];
    
    public static function create(
        string $connection = 'default',
        ?Configuration $config = null
    ): EntityManager {
        
        if (!isset(self::$instances[$connection])) {
            $config = $config ?: self::loadConfiguration($connection);
            
            self::$instances[$connection] = new EntityManager(
                self::createConnection($config),
                $config
            );
        }
        
        return self::$instances[$connection];
    }
    
    private static function loadConfiguration(string $connection): Configuration
    {
        $configFile = __DIR__ . "/config/{$connection}.php";
        
        if (!file_exists($configFile)) {
            throw new InvalidArgumentException(
                "Configuration file not found: {$configFile}"
            );
        }
        
        return new Configuration(require $configFile);
    }
    
    private static function createConnection(Configuration $config): DatabaseConnection
    {
        return new DatabaseConnection($config);
    }
}
```

---

## ➡️ Next Steps

Explore the following concepts:

1. 🏗️ [Architecture](architecture.md) - Architecture overview
2. 💉 [Dependency Injection](dependency-injection.md) - DI and services
3. 🗄️ [Entity Manager](../orm/entity-manager.md) - ORM usage
4. 🔧 [Query Builder](../query-builder/basic-queries.md) - Query building

---

## 🔗 Useful Links

- 🏠 [Back to README](../../README.md)
- ⬅️ [Query Builder](../query-builder/basic-queries.md)
- ➡️ [Architecture](architecture.md)
- 📖 [Complete Documentation](../README.md)