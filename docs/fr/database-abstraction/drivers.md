# Drivers de Base de Données

MulerTech Database utilise une architecture de drivers pour supporter différents systèmes de gestion de base de données (SGBD).

## Table des Matières
- [Architecture des drivers](#architecture-des-drivers)
- [Driver MySQL](#driver-mysql)
- [Configuration des drivers](#configuration-des-drivers)
- [Création d'un driver personnalisé](#création-dun-driver-personnalisé)
- [Optimisations spécifiques](#optimisations-spécifiques)
- [Gestion des versions](#gestion-des-versions)

## Architecture des drivers

### Interface commune

Tous les drivers implémentent l'interface `DatabaseDriverInterface` :

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Driver;

interface DatabaseDriverInterface
{
    public function connect(array $config): ConnectionInterface;
    
    public function disconnect(): void;
    
    public function getConnection(): ConnectionInterface;
    
    public function beginTransaction(): void;
    
    public function commit(): void;
    
    public function rollback(): void;
    
    public function execute(string $sql, array $params = []): ResultInterface;
    
    public function query(string $sql, array $params = []): ResultInterface;
    
    public function getLastInsertId(): int|string;
    
    public function getDatabasePlatform(): PlatformInterface;
    
    public function getSchemaManager(): SchemaManagerInterface;
    
    public function supportsFeature(string $feature): bool;
    
    public function getDriverName(): string;
    
    public function getVersion(): string;
}
```

### Factory de drivers

```php
use MulerTech\Database\Driver\DriverFactory;
use MulerTech\Database\Driver\MySQL\MySQLDriver;

class DriverFactory
{
    private static array $drivers = [
        'mysql' => MySQLDriver::class,
        'mariadb' => MySQLDriver::class,
        // Futurs drivers...
        // 'postgresql' => PostgreSQLDriver::class,
        // 'sqlite' => SQLiteDriver::class,
    ];

    public static function create(string $driverName, array $config): DatabaseDriverInterface
    {
        if (!isset(self::$drivers[$driverName])) {
            throw new UnsupportedDriverException("Driver '{$driverName}' not supported");
        }

        $driverClass = self::$drivers[$driverName];
        $driver = new $driverClass();
        $driver->connect($config);

        return $driver;
    }

    public static function registerDriver(string $name, string $className): void
    {
        if (!is_subclass_of($className, DatabaseDriverInterface::class)) {
            throw new InvalidArgumentException("Driver class must implement DatabaseDriverInterface");
        }

        self::$drivers[$name] = $className;
    }

    public static function getAvailableDrivers(): array
    {
        return array_keys(self::$drivers);
    }
}
```

## Driver MySQL

### Configuration de base

```php
use MulerTech\Database\Driver\MySQL\MySQLDriver;

$config = [
    'host' => 'localhost',
    'port' => 3306,
    'username' => 'user',
    'password' => 'password',
    'database' => 'my_database',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

$driver = new MySQLDriver();
$driver->connect($config);
```

### Configuration avancée

```php
$advancedConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'username' => 'user',
    'password' => 'password',
    'database' => 'my_database',
    
    // Encodage et collation
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    
    // Pool de connexions
    'pool' => [
        'min_connections' => 2,
        'max_connections' => 10,
        'idle_timeout' => 300,
        'validation_query' => 'SELECT 1'
    ],
    
    // SSL/TLS
    'ssl' => [
        'enabled' => true,
        'ca_cert' => '/path/to/ca-cert.pem',
        'client_cert' => '/path/to/client-cert.pem',
        'client_key' => '/path/to/client-key.pem',
        'verify_server_cert' => true
    ],
    
    // Timeouts
    'timeouts' => [
        'connect' => 5,
        'read' => 30,
        'write' => 30
    ],
    
    // Options PDO
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 30,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
    ]
];
```

### Fonctionnalités spécifiques MySQL

```php
class MySQLDriver implements DatabaseDriverInterface
{
    private PDO $connection;
    private MySQLPlatform $platform;
    private MySQLSchemaManager $schemaManager;

    public function connect(array $config): ConnectionInterface
    {
        $dsn = $this->buildDsn($config);
        
        $this->connection = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options'] ?? []
        );

        // Configuration post-connexion
        $this->configureConnection($config);

        return new MySQLConnection($this->connection);
    }

    private function buildDsn(array $config): string
    {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        
        if (isset($config['charset'])) {
            $dsn .= ";charset={$config['charset']}";
        }

        if (isset($config['unix_socket'])) {
            $dsn = "mysql:unix_socket={$config['unix_socket']};dbname={$config['database']}";
        }

        return $dsn;
    }

    private function configureConnection(array $config): void
    {
        // Mode SQL strict
        if ($config['strict_mode'] ?? true) {
            $this->connection->exec("SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
        }

        // Timezone
        if (isset($config['timezone'])) {
            $this->connection->exec("SET time_zone = '{$config['timezone']}'");
        }

        // Variables de session personnalisées
        if (isset($config['session_variables'])) {
            foreach ($config['session_variables'] as $variable => $value) {
                $this->connection->exec("SET {$variable} = {$value}");
            }
        }
    }

    public function supportsFeature(string $feature): bool
    {
        $supportedFeatures = [
            'transactions',
            'savepoints',
            'foreign_keys',
            'check_constraints',
            'json_columns',
            'fulltext_search',
            'spatial_indexes',
            'partitioning',
            'stored_procedures',
            'triggers',
            'views',
            'cte', // Common Table Expressions (MySQL 8.0+)
            'window_functions', // MySQL 8.0+
        ];

        return in_array($feature, $supportedFeatures);
    }

    public function getVersion(): string
    {
        $result = $this->connection->query("SELECT VERSION() as version");
        return $result->fetchColumn();
    }

    // Méthodes spécifiques MySQL
    public function getEngine(): string
    {
        $result = $this->connection->query("SHOW VARIABLES LIKE 'default_storage_engine'");
        return $result->fetchColumn(1);
    }

    public function optimizeTable(string $tableName): void
    {
        $this->connection->exec("OPTIMIZE TABLE `{$tableName}`");
    }

    public function analyzeTable(string $tableName): void
    {
        $this->connection->exec("ANALYZE TABLE `{$tableName}`");
    }

    public function repairTable(string $tableName): void
    {
        $this->connection->exec("REPAIR TABLE `{$tableName}`");
    }
}
```

## Configuration des drivers

### Configuration centralisée

```php
use MulerTech\Database\Configuration\DatabaseConfiguration;

class DatabaseConfiguration
{
    private array $connections = [];
    private string $defaultConnection = 'default';

    public function addConnection(string $name, array $config): void
    {
        $this->connections[$name] = $config;
    }

    public function getConnection(string $name = null): array
    {
        $name = $name ?? $this->defaultConnection;
        
        if (!isset($this->connections[$name])) {
            throw new ConnectionNotFoundException("Connection '{$name}' not found");
        }

        return $this->connections[$name];
    }

    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    // Configuration depuis un fichier
    public static function fromFile(string $filePath): self
    {
        $config = new self();
        $data = require $filePath;

        foreach ($data['connections'] as $name => $connectionConfig) {
            $config->addConnection($name, $connectionConfig);
        }

        if (isset($data['default'])) {
            $config->setDefaultConnection($data['default']);
        }

        return $config;
    }
}
```

### Fichier de configuration

```php
// config/database.php
return [
    'default' => 'mysql_primary',
    
    'connections' => [
        'mysql_primary' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'app'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict_mode' => true,
            'engine' => 'InnoDB',
        ],
        
        'mysql_read_replica' => [
            'driver' => 'mysql',
            'host' => env('DB_READ_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'app'),
            'username' => env('DB_READ_USERNAME', 'readonly'),
            'password' => env('DB_READ_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'read_only' => true,
        ],
        
        'test' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'app_test',
            'username' => 'test',
            'password' => 'test',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
];
```

## Création d'un driver personnalisé

### Structure d'un driver personnalisé

```php
<?php

declare(strict_types=1);

namespace App\Database\Driver\CustomDB;

use MulerTech\Database\Driver\DatabaseDriverInterface;
use MulerTech\Database\Connection\ConnectionInterface;

class CustomDBDriver implements DatabaseDriverInterface
{
    private $connection;
    private CustomDBPlatform $platform;
    private CustomDBSchemaManager $schemaManager;
    private array $config;

    public function connect(array $config): ConnectionInterface
    {
        $this->config = $config;
        
        // Logique de connexion spécifique
        $this->connection = $this->createConnection($config);
        
        // Initialiser les composants
        $this->platform = new CustomDBPlatform();
        $this->schemaManager = new CustomDBSchemaManager($this->connection);

        return new CustomDBConnection($this->connection);
    }

    public function getDriverName(): string
    {
        return 'customdb';
    }

    public function supportsFeature(string $feature): bool
    {
        // Définir les fonctionnalités supportées
        $supportedFeatures = [
            'transactions',
            'savepoints',
            // ... autres fonctionnalités
        ];

        return in_array($feature, $supportedFeatures);
    }

    private function createConnection(array $config)
    {
        // Implémentation spécifique de la connexion
        // Peut utiliser PDO, une extension native, ou une bibliothèque tierce
        return new CustomDBNativeConnection($config);
    }

    // Implémenter toutes les autres méthodes de l'interface...
}
```

### Platform personnalisée

```php
class CustomDBPlatform implements PlatformInterface
{
    public function getDateTimeFormatString(): string
    {
        return 'Y-m-d H:i:s';
    }

    public function getDateFormatString(): string
    {
        return 'Y-m-d';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return "`{$identifier}`"; // ou selon la syntaxe de votre SGBD
    }

    public function getCreateTableSQL(Table $table): array
    {
        // Générer le SQL de création de table spécifique
        return ["CREATE TABLE {$this->quoteIdentifier($table->getName())} (...)"];
    }

    // Autres méthodes platform-spécifiques...
}
```

### Enregistrement du driver

```php
// Dans votre bootstrap ou configuration
use MulerTech\Database\Driver\DriverFactory;

DriverFactory::registerDriver('customdb', CustomDBDriver::class);

// Utilisation
$config = [
    'driver' => 'customdb',
    'host' => 'custom-db-host',
    // ... autres paramètres
];

$driver = DriverFactory::create('customdb', $config);
```

## Optimisations spécifiques

### Pool de connexions MySQL

```php
class MySQLConnectionPool
{
    private array $connections = [];
    private array $config;
    private int $maxConnections;
    private int $minConnections;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->maxConnections = $config['pool']['max_connections'] ?? 10;
        $this->minConnections = $config['pool']['min_connections'] ?? 2;
        
        $this->initializePool();
    }

    public function getConnection(): MySQLConnection
    {
        // Récupérer une connexion disponible
        foreach ($this->connections as $connection) {
            if (!$connection->isInUse()) {
                $connection->setInUse(true);
                return $connection;
            }
        }

        // Créer une nouvelle connexion si possible
        if (count($this->connections) < $this->maxConnections) {
            $connection = $this->createConnection();
            $this->connections[] = $connection;
            return $connection;
        }

        // Attendre qu'une connexion se libère
        throw new ConnectionPoolExhaustedException("No available connections in pool");
    }

    public function releaseConnection(MySQLConnection $connection): void
    {
        $connection->setInUse(false);
    }

    private function initializePool(): void
    {
        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->connections[] = $this->createConnection();
        }
    }
}
```

### Optimisations MySQL spécifiques

```php
class MySQLOptimizer
{
    private MySQLDriver $driver;

    public function optimizeForReads(): void
    {
        $this->driver->execute("SET SESSION query_cache_type = ON");
        $this->driver->execute("SET SESSION read_buffer_size = 2097152"); // 2MB
    }

    public function optimizeForWrites(): void
    {
        $this->driver->execute("SET SESSION innodb_flush_log_at_trx_commit = 2");
        $this->driver->execute("SET SESSION sync_binlog = 0");
    }

    public function enableSlowQueryLog(): void
    {
        $this->driver->execute("SET GLOBAL slow_query_log = 'ON'");
        $this->driver->execute("SET GLOBAL long_query_time = 1");
    }

    public function analyzeSlowQueries(): array
    {
        $result = $this->driver->query("
            SELECT query_time, lock_time, rows_examined, rows_sent, sql_text
            FROM mysql.slow_log
            ORDER BY query_time DESC
            LIMIT 10
        ");

        return $result->fetchAll();
    }
}
```

## Gestion des versions

### Détection de version

```php
class DatabaseVersionManager
{
    private DatabaseDriverInterface $driver;

    public function getVersionInfo(): array
    {
        $version = $this->driver->getVersion();
        
        return [
            'version' => $version,
            'major' => $this->extractMajorVersion($version),
            'minor' => $this->extractMinorVersion($version),
            'patch' => $this->extractPatchVersion($version),
            'is_mariadb' => $this->isMariaDB($version),
        ];
    }

    public function supportsFeatureForVersion(string $feature): bool
    {
        $version = $this->getVersionInfo();

        $featureRequirements = [
            'json_columns' => ['mysql' => '5.7.8', 'mariadb' => '10.2.0'],
            'cte' => ['mysql' => '8.0.0', 'mariadb' => '10.2.1'],
            'window_functions' => ['mysql' => '8.0.0', 'mariadb' => '10.2.0'],
            'check_constraints' => ['mysql' => '8.0.16', 'mariadb' => '10.2.1'],
        ];

        if (!isset($featureRequirements[$feature])) {
            return false;
        }

        $dbType = $version['is_mariadb'] ? 'mariadb' : 'mysql';
        $requiredVersion = $featureRequirements[$feature][$dbType];

        return version_compare($version['version'], $requiredVersion, '>=');
    }

    private function isMariaDB(string $version): bool
    {
        return stripos($version, 'mariadb') !== false;
    }
}
```

---

**Voir aussi :**
- [Gestion des connexions](connections.md)
- [Gestion des transactions](transactions.md)
