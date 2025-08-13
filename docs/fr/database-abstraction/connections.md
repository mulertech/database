# Gestion des Connexions

La gestion des connexions dans MulerTech Database assure une utilisation efficace et sécurisée des ressources de base de données.

## Table des Matières
- [Types de connexions](#types-de-connexions)
- [Pool de connexions](#pool-de-connexions)
- [Connexions multiples](#connexions-multiples)
- [Reconnexion automatique](#reconnexion-automatique)
- [Monitoring et métriques](#monitoring-et-métriques)
- [Optimisations de performance](#optimisations-de-performance)

## Types de connexions

### Connexion simple

```php
use MulerTech\Database\Connection\Connection;
use MulerTech\Database\Driver\MySQL\MySQLDriver;

// Configuration de base
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'username' => 'user',
    'password' => 'password',
    'database' => 'myapp'
];

$driver = new MySQLDriver();
$connection = $driver->connect($config);

// Utilisation
$result = $connection->query('SELECT * FROM users WHERE active = ?', [1]);
```

### Connexion avec options avancées

```php
$advancedConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'username' => 'user',
    'password' => 'password',
    'database' => 'myapp',
    
    // Timeouts
    'connect_timeout' => 5,
    'read_timeout' => 30,
    'write_timeout' => 30,
    
    // Retry policy
    'retry_attempts' => 3,
    'retry_delay' => 1000, // millisecondes
    
    // Keep-alive
    'keepalive' => true,
    'keepalive_interval' => 60,
    
    // SSL
    'ssl_enabled' => true,
    'ssl_verify' => true,
    'ssl_ca' => '/path/to/ca.pem',
];

$connection = $driver->connect($advancedConfig);
```

### Interface de connexion

```php
interface ConnectionInterface
{
    public function query(string $sql, array $params = []): ResultInterface;
    
    public function execute(string $sql, array $params = []): int;
    
    public function beginTransaction(): void;
    
    public function commit(): void;
    
    public function rollback(): void;
    
    public function isTransactionActive(): bool;
    
    public function getLastInsertId(): int|string;
    
    public function isConnected(): bool;
    
    public function disconnect(): void;
    
    public function ping(): bool;
    
    public function getConnectionId(): string;
    
    public function getMetrics(): ConnectionMetrics;
}
```

## Pool de connexions

### Configuration du pool

```php
use MulerTech\Database\Connection\ConnectionPool;

$poolConfig = [
    'min_connections' => 2,
    'max_connections' => 10,
    'max_idle_time' => 300, // 5 minutes
    'validation_query' => 'SELECT 1',
    'validation_interval' => 30,
    'acquire_timeout' => 5000, // 5 secondes
    'cleanup_interval' => 60
];

$pool = new ConnectionPool($driver, $config, $poolConfig);
```

### Utilisation du pool

```php
class ConnectionPool
{
    private array $availableConnections = [];
    private array $busyConnections = [];
    private array $config;
    private DatabaseDriverInterface $driver;
    private int $totalConnections = 0;

    public function __construct(DatabaseDriverInterface $driver, array $config, array $poolConfig = [])
    {
        $this->driver = $driver;
        $this->config = array_merge($this->getDefaultPoolConfig(), $poolConfig);
        $this->initializePool();
    }

    public function getConnection(): ConnectionInterface
    {
        // Essayer de récupérer une connexion disponible
        if (!empty($this->availableConnections)) {
            $connection = array_pop($this->availableConnections);
            
            // Valider la connexion
            if ($this->validateConnection($connection)) {
                $this->busyConnections[$connection->getConnectionId()] = $connection;
                return $connection;
            } else {
                $this->closeConnection($connection);
            }
        }

        // Créer une nouvelle connexion si possible
        if ($this->totalConnections < $this->config['max_connections']) {
            $connection = $this->createNewConnection();
            $this->busyConnections[$connection->getConnectionId()] = $connection;
            $this->totalConnections++;
            return $connection;
        }

        // Attendre qu'une connexion se libère
        return $this->waitForAvailableConnection();
    }

    public function releaseConnection(ConnectionInterface $connection): void
    {
        $connectionId = $connection->getConnectionId();
        
        if (isset($this->busyConnections[$connectionId])) {
            unset($this->busyConnections[$connectionId]);
            
            // Vérifier si la connexion est encore valide
            if ($this->validateConnection($connection)) {
                $this->availableConnections[] = $connection;
            } else {
                $this->closeConnection($connection);
                $this->totalConnections--;
            }
        }
    }

    private function createNewConnection(): ConnectionInterface
    {
        return $this->driver->connect($this->config);
    }

    private function validateConnection(ConnectionInterface $connection): bool
    {
        try {
            return $connection->ping();
        } catch (Exception $e) {
            return false;
        }
    }

    private function waitForAvailableConnection(): ConnectionInterface
    {
        $timeout = $this->config['acquire_timeout'];
        $startTime = microtime(true);

        do {
            usleep(10000); // 10ms
            
            if (!empty($this->availableConnections)) {
                return $this->getConnection();
            }
            
            $elapsed = (microtime(true) - $startTime) * 1000;
        } while ($elapsed < $timeout);

        throw new ConnectionPoolTimeoutException(
            "Timeout waiting for available connection after {$timeout}ms"
        );
    }

    public function getPoolStats(): array
    {
        return [
            'total_connections' => $this->totalConnections,
            'available_connections' => count($this->availableConnections),
            'busy_connections' => count($this->busyConnections),
            'max_connections' => $this->config['max_connections'],
            'min_connections' => $this->config['min_connections'],
        ];
    }
}
```

### Nettoyage automatique du pool

```php
class ConnectionPoolCleaner
{
    private ConnectionPool $pool;
    private int $cleanupInterval;

    public function __construct(ConnectionPool $pool, int $cleanupInterval = 60)
    {
        $this->pool = $pool;
        $this->cleanupInterval = $cleanupInterval;
    }

    public function startCleanupScheduler(): void
    {
        // Utilisation d'un timer pour le nettoyage périodique
        $this->scheduleCleanup();
    }

    private function cleanup(): void
    {
        $stats = $this->pool->getPoolStats();
        $idleConnections = $this->pool->getIdleConnections();

        foreach ($idleConnections as $connection) {
            $idleTime = $connection->getIdleTime();
            
            if ($idleTime > $this->pool->getMaxIdleTime()) {
                // Garder au minimum le nombre minimum de connexions
                if ($stats['total_connections'] > $stats['min_connections']) {
                    $this->pool->closeConnection($connection);
                }
            }
        }
    }
}
```

## Connexions multiples

### Gestionnaire de connexions multiples

```php
use MulerTech\Database\Connection\ConnectionManager;

class ConnectionManager
{
    private array $connections = [];
    private array $configs = [];
    private string $defaultConnection = 'default';

    public function addConnection(string $name, array $config): void
    {
        $this->configs[$name] = $config;
    }

    public function getConnection(string $name = null): ConnectionInterface
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }

        return $this->connections[$name];
    }

    private function createConnection(string $name): ConnectionInterface
    {
        if (!isset($this->configs[$name])) {
            throw new ConnectionNotFoundException("Connection '{$name}' not configured");
        }

        $config = $this->configs[$name];
        $driver = DriverFactory::create($config['driver'], $config);
        
        return $driver->getConnection();
    }

    public function closeAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }
        $this->connections = [];
    }
}
```

### Connexions Read/Write

```php
class ReadWriteConnectionManager
{
    private ConnectionInterface $writeConnection;
    private array $readConnections = [];
    private int $currentReadIndex = 0;

    public function __construct(array $writeConfig, array $readConfigs = [])
    {
        // Connexion principale pour les écritures
        $writeDriver = DriverFactory::create($writeConfig['driver'], $writeConfig);
        $this->writeConnection = $writeDriver->getConnection();

        // Connexions en lecture seule (replicas)
        foreach ($readConfigs as $readConfig) {
            $readDriver = DriverFactory::create($readConfig['driver'], $readConfig);
            $this->readConnections[] = $readDriver->getConnection();
        }

        // Si pas de replicas, utiliser la connexion principale
        if (empty($this->readConnections)) {
            $this->readConnections[] = $this->writeConnection;
        }
    }

    public function getWriteConnection(): ConnectionInterface
    {
        return $this->writeConnection;
    }

    public function getReadConnection(): ConnectionInterface
    {
        // Load balancing round-robin simple
        $connection = $this->readConnections[$this->currentReadIndex];
        $this->currentReadIndex = ($this->currentReadIndex + 1) % count($this->readConnections);

        // Vérifier que la connexion est active
        if (!$connection->ping()) {
            // Essayer les autres connexions ou fallback sur write
            return $this->findHealthyReadConnection() ?? $this->writeConnection;
        }

        return $connection;
    }

    private function findHealthyReadConnection(): ?ConnectionInterface
    {
        foreach ($this->readConnections as $connection) {
            if ($connection->ping()) {
                return $connection;
            }
        }
        return null;
    }

    public function executeQuery(string $sql, array $params = [], bool $forceWrite = false): ResultInterface
    {
        $connection = $forceWrite ? $this->getWriteConnection() : $this->getReadConnection();
        return $connection->query($sql, $params);
    }
}
```

## Reconnexion automatique

### Stratégie de reconnexion

```php
class ReconnectionStrategy
{
    private ConnectionInterface $connection;
    private array $config;
    private int $maxRetries;
    private int $baseDelay;

    public function __construct(ConnectionInterface $connection, array $config)
    {
        $this->connection = $connection;
        $this->config = $config;
        $this->maxRetries = $config['retry_attempts'] ?? 3;
        $this->baseDelay = $config['retry_delay'] ?? 1000;
    }

    public function executeWithRetry(callable $operation)
    {
        $attempt = 0;
        $lastException = null;

        do {
            try {
                if (!$this->connection->isConnected() && $attempt > 0) {
                    $this->reconnect();
                }

                return $operation($this->connection);

            } catch (ConnectionException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt <= $this->maxRetries) {
                    $delay = $this->calculateDelay($attempt);
                    usleep($delay * 1000); // Convert to microseconds
                }
            }
        } while ($attempt <= $this->maxRetries);

        throw new MaxRetriesExceededException(
            "Operation failed after {$this->maxRetries} attempts",
            0,
            $lastException
        );
    }

    private function calculateDelay(int $attempt): int
    {
        // Exponential backoff with jitter
        $delay = $this->baseDelay * pow(2, $attempt - 1);
        $jitter = mt_rand(0, (int)($delay * 0.1));
        
        return $delay + $jitter;
    }

    private function reconnect(): void
    {
        $this->connection->disconnect();
        
        $driver = DriverFactory::create($this->config['driver'], $this->config);
        $this->connection = $driver->connect($this->config);
    }
}
```

### Wrapper de connexion avec reconnexion

```php
class ReconnectableConnection implements ConnectionInterface
{
    private ConnectionInterface $connection;
    private ReconnectionStrategy $strategy;

    public function __construct(ConnectionInterface $connection, ReconnectionStrategy $strategy)
    {
        $this->connection = $connection;
        $this->strategy = $strategy;
    }

    public function query(string $sql, array $params = []): ResultInterface
    {
        return $this->strategy->executeWithRetry(
            fn($conn) => $conn->query($sql, $params)
        );
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->strategy->executeWithRetry(
            fn($conn) => $conn->execute($sql, $params)
        );
    }

    public function beginTransaction(): void
    {
        $this->strategy->executeWithRetry(
            fn($conn) => $conn->beginTransaction()
        );
    }

    // Implémenter les autres méthodes avec la même logique...
}
```

## Monitoring et métriques

### Métriques de connexion

```php
class ConnectionMetrics
{
    private int $queriesExecuted = 0;
    private float $totalQueryTime = 0.0;
    private int $connectionsCreated = 0;
    private int $connectionsClosed = 0;
    private int $connectionErrors = 0;
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function recordQuery(float $executionTime): void
    {
        $this->queriesExecuted++;
        $this->totalQueryTime += $executionTime;
    }

    public function recordConnectionCreated(): void
    {
        $this->connectionsCreated++;
    }

    public function recordConnectionClosed(): void
    {
        $this->connectionsClosed++;
    }

    public function recordConnectionError(): void
    {
        $this->connectionErrors++;
    }

    public function getAverageQueryTime(): float
    {
        return $this->queriesExecuted > 0 
            ? $this->totalQueryTime / $this->queriesExecuted 
            : 0.0;
    }

    public function getQueriesPerSecond(): float
    {
        $uptime = time() - $this->createdAt->getTimestamp();
        return $uptime > 0 ? $this->queriesExecuted / $uptime : 0.0;
    }

    public function toArray(): array
    {
        return [
            'queries_executed' => $this->queriesExecuted,
            'total_query_time' => $this->totalQueryTime,
            'average_query_time' => $this->getAverageQueryTime(),
            'queries_per_second' => $this->getQueriesPerSecond(),
            'connections_created' => $this->connectionsCreated,
            'connections_closed' => $this->connectionsClosed,
            'connection_errors' => $this->connectionErrors,
            'uptime' => time() - $this->createdAt->getTimestamp(),
        ];
    }
}
```

### Monitoring de santé

```php
class ConnectionHealthMonitor
{
    private array $connections = [];
    private array $healthChecks = [];

    public function addConnection(string $name, ConnectionInterface $connection): void
    {
        $this->connections[$name] = $connection;
    }

    public function registerHealthCheck(string $name, callable $check): void
    {
        $this->healthChecks[$name] = $check;
    }

    public function checkHealth(): array
    {
        $results = [];

        foreach ($this->connections as $name => $connection) {
            $results[$name] = $this->checkConnectionHealth($name, $connection);
        }

        return $results;
    }

    private function checkConnectionHealth(string $name, ConnectionInterface $connection): array
    {
        $start = microtime(true);
        $status = 'healthy';
        $error = null;

        try {
            // Test de base : ping
            if (!$connection->ping()) {
                $status = 'unhealthy';
                $error = 'Connection ping failed';
            }

            // Tests personnalisés
            foreach ($this->healthChecks as $checkName => $check) {
                if (!$check($connection)) {
                    $status = 'unhealthy';
                    $error = "Health check '{$checkName}' failed";
                    break;
                }
            }

        } catch (Exception $e) {
            $status = 'error';
            $error = $e->getMessage();
        }

        return [
            'status' => $status,
            'response_time' => microtime(true) - $start,
            'error' => $error,
            'metrics' => $connection->getMetrics()->toArray(),
        ];
    }

    public function getUnhealthyConnections(): array
    {
        $health = $this->checkHealth();
        
        return array_filter($health, function($result) {
            return $result['status'] !== 'healthy';
        });
    }
}
```

## Optimisations de performance

### Configuration optimisée pour MySQL

```php
class MySQLConnectionOptimizer
{
    public static function getOptimizedConfig(string $environment = 'production'): array
    {
        $baseConfig = [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ];

        switch ($environment) {
            case 'production':
                return array_merge($baseConfig, [
                    'pool' => [
                        'min_connections' => 5,
                        'max_connections' => 20,
                        'max_idle_time' => 300,
                    ],
                    'options' => array_merge($baseConfig['options'], [
                        PDO::ATTR_PERSISTENT => true,
                        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_TRANS_TABLES'"
                    ]),
                ]);

            case 'development':
                return array_merge($baseConfig, [
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 5,
                        'max_idle_time' => 60,
                    ],
                    'options' => array_merge($baseConfig['options'], [
                        PDO::ATTR_PERSISTENT => false,
                    ]),
                ]);

            default:
                return $baseConfig;
        }
    }
}
```

### Cache de connexions

```php
class ConnectionCache
{
    private static array $cache = [];
    private static int $ttl = 3600; // 1 heure

    public static function get(string $key): ?ConnectionInterface
    {
        if (isset(self::$cache[$key])) {
            $cached = self::$cache[$key];
            
            if ($cached['expires'] > time()) {
                return $cached['connection'];
            } else {
                unset(self::$cache[$key]);
            }
        }

        return null;
    }

    public static function set(string $key, ConnectionInterface $connection, int $ttl = null): void
    {
        $ttl = $ttl ?? self::$ttl;
        
        self::$cache[$key] = [
            'connection' => $connection,
            'expires' => time() + $ttl,
        ];
    }

    public static function clear(): void
    {
        foreach (self::$cache as $cached) {
            $cached['connection']->disconnect();
        }
        
        self::$cache = [];
    }
}
```

---

**Voir aussi :**
- [Drivers de base de données](drivers.md)
- [Gestion des transactions](transactions.md)
