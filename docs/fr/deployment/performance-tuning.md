# Optimisation des Performances - MulerTech Database

Ce guide couvre les techniques d'optimisation des performances pour MulerTech Database ORM en environnement de production.

## 📋 Table des matières

- [Profilage et mesures](#profilage-et-mesures)
- [Optimisations de base de données](#optimisations-de-base-de-données)
- [Optimisations PHP](#optimisations-php)
- [Stratégies de cache](#stratégies-de-cache)
- [Optimisation des requêtes](#optimisation-des-requêtes)
- [Gestion de la mémoire](#gestion-de-la-mémoire)
- [Connection pooling](#connection-pooling)
- [Monitoring continu](#monitoring-continu)

## Profilage et mesures

### Métriques de performance essentielles

```php
<?php

declare(strict_types=1);

namespace App\Service\Performance;

use MulerTech\Database\ORM\EmEngine;
use Psr\Log\LoggerInterface;

/**
 * Service de profilage des performances
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PerformanceProfiler
{
    private array $metrics = [];
    private array $queryLog = [];
    private float $startTime;

    public function __construct(
        private readonly EmEngine $em,
        private readonly LoggerInterface $logger
    ) {
        $this->startTime = microtime(true);
    }

    /**
     * Démarre le profilage d'une opération
     */
    public function startProfiling(string $operation): void
    {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'query_count_start' => count($this->queryLog)
        ];
    }

    /**
     * Termine le profilage d'une opération
     */
    public function endProfiling(string $operation): array
    {
        if (!isset($this->metrics[$operation])) {
            throw new \InvalidArgumentException("Operation '{$operation}' was not started");
        }

        $start = $this->metrics[$operation];
        $result = [
            'operation' => $operation,
            'duration_ms' => round((microtime(true) - $start['start_time']) * 1000, 2),
            'memory_used_mb' => round((memory_get_usage(true) - $start['start_memory']) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'queries_executed' => count($this->queryLog) - $start['query_count_start'],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->metrics[$operation] = $result;

        // Log des opérations lentes
        if ($result['duration_ms'] > 1000) {
            $this->logger->warning('Slow operation detected', $result);
        }

        return $result;
    }

    /**
     * Enregistre une requête exécutée
     */
    public function logQuery(string $sql, array $params = [], float $duration = 0.0): void
    {
        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'duration_ms' => round($duration * 1000, 2),
            'timestamp' => microtime(true)
        ];

        // Alerte pour les requêtes lentes
        if ($duration > 0.1) {
            $this->logger->warning('Slow query detected', [
                'sql' => $sql,
                'duration_ms' => round($duration * 1000, 2),
                'params' => $params
            ]);
        }
    }

    /**
     * Génère un rapport de performance
     */
    public function generateReport(): array
    {
        $totalQueries = count($this->queryLog);
        $totalDuration = array_sum(array_column($this->queryLog, 'duration_ms'));
        $slowQueries = array_filter($this->queryLog, fn($q) => $q['duration_ms'] > 100);

        return [
            'summary' => [
                'total_execution_time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ],
            'database' => [
                'total_queries' => $totalQueries,
                'total_query_time_ms' => $totalDuration,
                'average_query_time_ms' => $totalQueries > 0 ? round($totalDuration / $totalQueries, 2) : 0,
                'slow_queries_count' => count($slowQueries)
            ],
            'operations' => $this->metrics,
            'slow_queries' => array_slice($slowQueries, 0, 10), // Top 10 des requêtes lentes
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}
```

### Intégration du profilage

```php
<?php

use App\Service\Performance\PerformanceProfiler;

// Dans votre bootstrap ou service container
$profiler = new PerformanceProfiler($em, $logger);

// Exemple d'utilisation
$profiler->startProfiling('user_listing');

$users = $em->getRepository(User::class)
            ->createQueryBuilder()
            ->select('*')
            ->where('active = ?')
            ->setParameter(0, true)
            ->getQuery()
            ->getResult();

$metrics = $profiler->endProfiling('user_listing');

// Génération du rapport final
$report = $profiler->generateReport();
```

## Optimisations de base de données

### Configuration MySQL optimisée

**`/etc/mysql/mysql.conf.d/performance.cnf`**
```ini
[mysqld]
# Configuration générale
default_storage_engine = InnoDB
sql_mode = STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO

# Optimisations InnoDB
innodb_buffer_pool_size = 4G                    # 70-80% de la RAM disponible
innodb_buffer_pool_instances = 8                # 1 instance par GB de buffer pool
innodb_log_file_size = 1G                       # 25% du buffer pool
innodb_log_buffer_size = 64M
innodb_flush_log_at_trx_commit = 2              # Performance vs durabilité
innodb_flush_method = O_DIRECT                  # Évite le double buffering
innodb_file_per_table = 1

# Optimisations des connexions
max_connections = 300
max_user_connections = 280
thread_cache_size = 16
table_open_cache = 4000
table_definition_cache = 2000

# Optimisations des requêtes
tmp_table_size = 256M
max_heap_table_size = 256M
join_buffer_size = 8M
sort_buffer_size = 4M
read_buffer_size = 2M
read_rnd_buffer_size = 4M

# Cache des requêtes (MySQL 5.7 uniquement)
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 8M

# Optimisations des logs
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1
log_queries_not_using_indexes = 1

# Optimisations MyISAM (si utilisé)
key_buffer_size = 256M
myisam_sort_buffer_size = 128M
```

### Index et optimisations de schéma

```sql
-- Exemples d'optimisations d'index pour MulerTech Database

-- Index composites pour les requêtes fréquentes
CREATE INDEX idx_users_status_created ON users (status, created_at);
CREATE INDEX idx_orders_customer_status ON orders (customer_id, status, created_at);
CREATE INDEX idx_products_category_active ON products (category_id, is_active, price);

-- Index partiels pour optimiser l'espace
CREATE INDEX idx_users_active ON users (created_at) WHERE status = 'active';
CREATE INDEX idx_orders_pending ON orders (created_at) WHERE status IN ('pending', 'processing');

-- Index fonctionnels
CREATE INDEX idx_users_email_lower ON users ((LOWER(email)));
CREATE INDEX idx_products_name_search ON products ((MATCH(name, description) AGAINST ('' IN NATURAL LANGUAGE MODE)));

-- Optimisation des types de données
ALTER TABLE users 
MODIFY COLUMN status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'pending',
MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
MODIFY COLUMN email VARCHAR(255) NOT NULL;

-- Partitioning pour les grandes tables
ALTER TABLE orders 
PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2022 VALUES LESS THAN (2023),
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### Analyse et optimisation des requêtes

```php
<?php

declare(strict_types=1);

namespace App\Service\Performance;

use MulerTech\Database\ORM\EmEngine;

/**
 * Analyseur de performance des requêtes
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryAnalyzer
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * Analyse une requête avec EXPLAIN
     */
    public function analyzeQuery(string $sql, array $params = []): array
    {
        // Exécution d'EXPLAIN sur la requête
        $explainSql = "EXPLAIN FORMAT=JSON " . $sql;
        $result = $this->em->getConnection()->execute($explainSql, $params);
        
        $explainData = json_decode($result[0]['EXPLAIN'], true);
        
        return [
            'query' => $sql,
            'parameters' => $params,
            'explain_data' => $explainData,
            'analysis' => $this->parseExplainData($explainData),
            'recommendations' => $this->generateRecommendations($explainData)
        ];
    }

    /**
     * Identifie les requêtes problématiques
     */
    public function findProblematicQueries(): array
    {
        $problematicQueries = [];
        
        // Requêtes sans index
        $noIndexQueries = $this->em->getConnection()->execute("
            SELECT sql_text, exec_count, avg_timer_wait/1000000000 as avg_duration_sec
            FROM performance_schema.events_statements_summary_by_digest 
            WHERE no_index_used_count > 0 
            ORDER BY avg_timer_wait DESC 
            LIMIT 10
        ");
        
        $problematicQueries['no_index'] = $noIndexQueries;
        
        // Requêtes lentes
        $slowQueries = $this->em->getConnection()->execute("
            SELECT sql_text, exec_count, avg_timer_wait/1000000000 as avg_duration_sec,
                   sum_rows_examined/exec_count as avg_rows_examined
            FROM performance_schema.events_statements_summary_by_digest 
            WHERE avg_timer_wait > 1000000000  -- Plus d'1 seconde
            ORDER BY avg_timer_wait DESC 
            LIMIT 10
        ");
        
        $problematicQueries['slow'] = $slowQueries;
        
        return $problematicQueries;
    }

    /**
     * Parse les données d'EXPLAIN
     */
    private function parseExplainData(array $explainData): array
    {
        $analysis = [
            'cost_info' => [],
            'table_scans' => [],
            'index_usage' => [],
            'join_info' => []
        ];
        
        $queryBlock = $explainData['query_block'] ?? [];
        
        if (isset($queryBlock['cost_info'])) {
            $analysis['cost_info'] = $queryBlock['cost_info'];
        }
        
        // Analyse des tables
        if (isset($queryBlock['table'])) {
            $table = $queryBlock['table'];
            
            if ($table['access_type'] === 'ALL') {
                $analysis['table_scans'][] = $table['table_name'];
            }
            
            if (isset($table['key'])) {
                $analysis['index_usage'][] = [
                    'table' => $table['table_name'],
                    'key' => $table['key'],
                    'key_length' => $table['key_length'] ?? null
                ];
            }
        }
        
        return $analysis;
    }

    /**
     * Génère des recommandations d'optimisation
     */
    private function generateRecommendations(array $explainData): array
    {
        $recommendations = [];
        
        $queryBlock = $explainData['query_block'] ?? [];
        
        // Vérification des coûts
        if (isset($queryBlock['cost_info']['read_cost'])) {
            $readCost = $queryBlock['cost_info']['read_cost'];
            if ($readCost > 1000) {
                $recommendations[] = "Coût de lecture élevé ({$readCost}). Considérez l'ajout d'index appropriés.";
            }
        }
        
        // Vérification des table scans
        if (isset($queryBlock['table']['access_type']) && $queryBlock['table']['access_type'] === 'ALL') {
            $tableName = $queryBlock['table']['table_name'];
            $recommendations[] = "Table scan détecté sur '{$tableName}'. Ajoutez un index sur les colonnes de la clause WHERE.";
        }
        
        // Vérification des jointures
        if (isset($queryBlock['nested_loop'])) {
            foreach ($queryBlock['nested_loop'] as $table) {
                if (isset($table['table']['access_type']) && $table['table']['access_type'] === 'ALL') {
                    $tableName = $table['table']['table_name'];
                    $recommendations[] = "Jointure inefficace sur '{$tableName}'. Optimisez les index de jointure.";
                }
            }
        }
        
        return $recommendations;
    }
}
```

## Optimisations PHP

### Configuration PHP pour performance

**`php.ini` optimisé**
```ini
[PHP]
; Optimisations générales
memory_limit = 512M
max_execution_time = 300
max_input_time = 300

; OPcache - Crucial pour les performances
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0          ; Production uniquement
opcache.revalidate_freq = 0              ; Production uniquement
opcache.save_comments = 1
opcache.enable_file_override = 1
opcache.optimization_level = 0x7FFEBFFF
opcache.blacklist_filename = /etc/php/opcache-blacklist.txt

; JIT (PHP 8.0+)
opcache.jit = 1255
opcache.jit_buffer_size = 128M

; Préchargement (PHP 7.4+)
opcache.preload = /var/www/html/preload.php
opcache.preload_user = www-data

; Réalpath cache
realpath_cache_size = 4096K
realpath_cache_ttl = 600

; Sessions optimisées
session.save_handler = redis
session.save_path = "tcp://redis:6379"
session.gc_probability = 0               ; Désactivé, géré par Redis
session.serialize_handler = igbinary    ; Plus rapide que PHP
```

### Script de préchargement

**`preload.php`**
```php
<?php

declare(strict_types=1);

/**
 * Script de préchargement OPcache pour MulerTech Database
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */

// Classes critiques à précharger
$classesToPreload = [
    // Core ORM
    'MulerTech\\Database\\ORM\\EmEngine',
    'MulerTech\\Database\\ORM\\Repository\\EntityRepository',
    'MulerTech\\Database\\Query\\Builder\\QueryBuilder',
    'MulerTech\\Database\\Mapping\\MetadataRegistry',
    
    // Database layer
    'MulerTech\\Database\\Database\\MySQLDriver',
    'MulerTech\\Database\\Database\\Connection',
    
    // Cache
    'MulerTech\\Database\\Core\\Cache\\RedisCache',
    'MulerTech\\Database\\Core\\Cache\\FileCache',
    
    // Entités fréquemment utilisées
    'App\\Entity\\User',
    'App\\Entity\\Product',
    'App\\Entity\\Order',
    'App\\Entity\\Category',
    
    // Services critiques
    'App\\Service\\UserService',
    'App\\Service\\ProductService',
    'App\\Service\\OrderService'
];

// Préchargement des classes
foreach ($classesToPreload as $class) {
    if (class_exists($class)) {
        opcache_compile_file(
            (new ReflectionClass($class))->getFileName()
        );
    }
}

// Préchargement des fichiers de configuration fréquemment utilisés
$configFiles = [
    __DIR__ . '/config/database.php',
    __DIR__ . '/config/cache.php',
    __DIR__ . '/config/services.php'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        opcache_compile_file($file);
    }
}

echo "Préchargement OPcache terminé : " . count($classesToPreload) . " classes préchargées\n";
```

### Optimisations du code applicatif

```php
<?php

declare(strict_types=1);

namespace App\Service\Performance;

use MulerTech\Database\ORM\EmEngine;

/**
 * Optimisations applicatives pour MulerTech Database
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ApplicationOptimizer
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * Chargement optimisé en lot
     */
    public function batchLoad(array $entityIds, string $entityClass): array
    {
        // Évite les requêtes N+1 en chargeant en une seule fois
        return $this->em->getRepository($entityClass)
                       ->createQueryBuilder()
                       ->select('*')
                       ->where('id IN (?)')
                       ->setParameter(0, $entityIds)
                       ->getQuery()
                       ->getResult();
    }

    /**
     * Pagination efficace pour grandes datasets
     */
    public function efficientPagination(string $entityClass, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        
        // Utilisation de LIMIT avec OFFSET optimisé
        if ($offset > 10000) {
            // Pour les offsets importants, utiliser une approche basée sur l'ID
            return $this->paginateByLastId($entityClass, $page, $limit);
        }
        
        return $this->em->getRepository($entityClass)
                       ->createQueryBuilder()
                       ->select('*')
                       ->orderBy('id', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->getQuery()
                       ->getResult();
    }

    /**
     * Pagination basée sur l'ID pour éviter les OFFSET coûteux
     */
    private function paginateByLastId(string $entityClass, int $page, int $limit): array
    {
        $lastId = $this->getLastIdForPage($entityClass, $page, $limit);
        
        return $this->em->getRepository($entityClass)
                       ->createQueryBuilder()
                       ->select('*')
                       ->where('id < ?')
                       ->orderBy('id', 'DESC')
                       ->limit($limit)
                       ->setParameter(0, $lastId)
                       ->getQuery()
                       ->getResult();
    }

    /**
     * Requêtes avec projection pour limiter les données
     */
    public function lightweightProjection(string $entityClass, array $fields): array
    {
        return $this->em->getRepository($entityClass)
                       ->createQueryBuilder()
                       ->select(implode(', ', $fields))
                       ->getQuery()
                       ->getArrayResult();
    }

    /**
     * Insertion en lot optimisée
     */
    public function bulkInsert(array $entities, int $batchSize = 1000): void
    {
        $count = 0;
        
        foreach ($entities as $entity) {
            $this->em->persist($entity);
            
            if (++$count % $batchSize === 0) {
                $this->em->flush();
                $this->em->clear(); // Libère la mémoire
            }
        }
        
        // Flush final pour les entités restantes
        if ($count % $batchSize !== 0) {
            $this->em->flush();
            $this->em->clear();
        }
    }

    private function getLastIdForPage(string $entityClass, int $page, int $limit): int
    {
        $offset = ($page - 1) * $limit;
        
        $result = $this->em->getRepository($entityClass)
                          ->createQueryBuilder()
                          ->select('id')
                          ->orderBy('id', 'DESC')
                          ->limit(1)
                          ->offset($offset - 1)
                          ->getQuery()
                          ->getSingleScalarResult();
        
        return $result ?? PHP_INT_MAX;
    }
}
```

## Stratégies de cache

### Configuration Redis optimisée

**`redis.conf`**
```bash
# Optimisations mémoire
maxmemory 2gb
maxmemory-policy allkeys-lru
maxmemory-samples 10

# Optimisations réseau
tcp-nodelay yes
tcp-keepalive 300

# Optimisations de persistance
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error no
rdbcompression yes
rdbchecksum yes

# Optimisations d'écriture
appendonly yes
appendfsync everysec
no-appendfsync-on-rewrite no
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

# Optimisations client
timeout 300
client-output-buffer-limit normal 0 0 0
client-output-buffer-limit replica 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60
```

### Cache multi-niveau

```php
<?php

declare(strict_types=1);

namespace App\Service\Cache;

use MulerTech\Database\Core\Cache\CacheInterface;

/**
 * Cache multi-niveau pour optimiser les performances
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MultiLevelCache implements CacheInterface
{
    public function __construct(
        private readonly CacheInterface $l1Cache,  // Cache en mémoire (APCu)
        private readonly CacheInterface $l2Cache   // Cache Redis
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        // Vérification L1 (mémoire locale)
        $value = $this->l1Cache->get($key);
        if ($value !== null) {
            return $value;
        }
        
        // Vérification L2 (Redis)
        $value = $this->l2Cache->get($key);
        if ($value !== null) {
            // Promotion vers L1
            $this->l1Cache->set($key, $value, 300); // 5 minutes en L1
            return $value;
        }
        
        return $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        // Stockage en L1 et L2
        $l1Result = $this->l1Cache->set($key, $value, min($ttl ?? 3600, 600));
        $l2Result = $this->l2Cache->set($key, $value, $ttl);
        
        return $l1Result && $l2Result;
    }

    public function delete(string $key): bool
    {
        $l1Result = $this->l1Cache->delete($key);
        $l2Result = $this->l2Cache->delete($key);
        
        return $l1Result && $l2Result;
    }

    public function clear(): bool
    {
        $l1Result = $this->l1Cache->clear();
        $l2Result = $this->l2Cache->clear();
        
        return $l1Result && $l2Result;
    }

    public function has(string $key): bool
    {
        return $this->l1Cache->has($key) || $this->l2Cache->has($key);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }

    public function deleteByPattern(string $pattern): int
    {
        $l1Count = $this->l1Cache->deleteByPattern($pattern);
        $l2Count = $this->l2Cache->deleteByPattern($pattern);
        
        return max($l1Count, $l2Count);
    }
}
```

## Optimisation des requêtes

### Query Builder optimisé

```php
<?php

declare(strict_types=1);

namespace App\Service\Query;

use MulerTech\Database\ORM\EmEngine;

/**
 * Optimiseur de requêtes pour MulerTech Database
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryOptimizer
{
    public function __construct(
        private readonly EmEngine $em
    ) {}

    /**
     * Requête optimisée avec index hints
     */
    public function optimizedProductSearch(array $filters, int $limit = 20): array
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->select([
                'p.id',
                'p.name',
                'p.price',
                'p.stock_quantity',
                'c.name as category_name'
            ])
           ->from('products', 'p')
           ->leftJoin('categories', 'c', 'c.id = p.category_id')
           ->where('p.is_active = 1');
        
        // Ajout de hints d'index
        $qb->addHint('USE INDEX', 'idx_products_active_category');
        
        // Filtres optimisés
        if (isset($filters['category_id'])) {
            $qb->andWhere('p.category_id = ?')
               ->setParameter('category_id', $filters['category_id']);
        }
        
        if (isset($filters['price_min'])) {
            $qb->andWhere('p.price >= ?')
               ->setParameter('price_min', $filters['price_min']);
        }
        
        if (isset($filters['price_max'])) {
            $qb->andWhere('p.price <= ?')
               ->setParameter('price_max', $filters['price_max']);
        }
        
        // Recherche full-text optimisée
        if (isset($filters['search'])) {
            $qb->andWhere('MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE)')
               ->setParameter('search', $filters['search']);
        }
        
        return $qb->orderBy('p.created_at', 'DESC')
                  ->limit($limit)
                  ->getQuery()
                  ->getArrayResult();
    }

    /**
     * Agrégation optimisée
     */
    public function getOrderStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->em->createQueryBuilder()
                       ->select([
                           'DATE(o.created_at) as order_date',
                           'COUNT(*) as order_count',
                           'SUM(o.total) as total_revenue',
                           'AVG(o.total) as average_order_value',
                           'COUNT(DISTINCT o.customer_id) as unique_customers'
                       ])
                       ->from('orders', 'o')
                       ->where('o.created_at BETWEEN ? AND ?')
                       ->andWhere('o.status NOT IN (?, ?)')
                       ->groupBy('DATE(o.created_at)')
                       ->orderBy('order_date', 'ASC')
                       ->setParameter(0, $from->format('Y-m-d'))
                       ->setParameter(1, $to->format('Y-m-d'))
                       ->setParameter(2, 'cancelled')
                       ->setParameter(3, 'pending')
                       ->getQuery()
                       ->getArrayResult();
    }

    /**
     * Requête avec subquery optimisée
     */
    public function getTopCustomersByRevenue(int $limit = 10): array
    {
        return $this->em->createQueryBuilder()
                       ->select([
                           'c.id',
                           'c.name',
                           'c.email',
                           'customer_stats.total_spent',
                           'customer_stats.order_count'
                       ])
                       ->from('customers', 'c')
                       ->innerJoin(
                           '(SELECT customer_id, 
                                    SUM(total) as total_spent,
                                    COUNT(*) as order_count
                             FROM orders 
                             WHERE status = "completed"
                             GROUP BY customer_id
                             HAVING total_spent > 1000) customer_stats',
                           'customer_stats',
                           'customer_stats.customer_id = c.id'
                       )
                       ->orderBy('customer_stats.total_spent', 'DESC')
                       ->limit($limit)
                       ->getQuery()
                       ->getArrayResult();
    }
}
```

## Gestion de la mémoire

### Optimisation de la mémoire PHP

```php
<?php

declare(strict_types=1);

namespace App\Service\Performance;

use MulerTech\Database\ORM\EmEngine;

/**
 * Gestionnaire optimisé de mémoire
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MemoryManager
{
    private int $maxMemoryUsage;
    private int $batchSize;

    public function __construct(
        private readonly EmEngine $em,
        int $maxMemoryUsagePercent = 80
    ) {
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $this->maxMemoryUsage = (int) ($memoryLimit * ($maxMemoryUsagePercent / 100));
        $this->batchSize = 1000;
    }

    /**
     * Traitement par batch avec gestion mémoire
     */
    public function processBatch(array $items, callable $processor): void
    {
        $chunks = array_chunk($items, $this->batchSize);
        
        foreach ($chunks as $chunk) {
            // Vérification de la mémoire avant traitement
            if (memory_get_usage(true) > $this->maxMemoryUsage) {
                $this->freeMemory();
            }
            
            foreach ($chunk as $item) {
                $processor($item);
            }
            
            // Nettoyage après chaque batch
            $this->em->clear();
            
            // Force le garbage collector
            if (gc_collect_cycles() > 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Streaming de grandes datasets
     */
    public function streamLargeDataset(string $entityClass, callable $processor): void
    {
        $lastId = 0;
        $batchSize = $this->batchSize;
        
        do {
            $entities = $this->em->getRepository($entityClass)
                                ->createQueryBuilder()
                                ->select('*')
                                ->where('id > ?')
                                ->orderBy('id', 'ASC')
                                ->limit($batchSize)
                                ->setParameter(0, $lastId)
                                ->getQuery()
                                ->getResult();
            
            foreach ($entities as $entity) {
                $processor($entity);
                $lastId = $entity->getId();
            }
            
            // Nettoyage mémoire
            $this->em->clear();
            unset($entities);
            
            $this->checkMemoryUsage();
            
        } while (count($entities) === $batchSize);
    }

    /**
     * Libération agressive de la mémoire
     */
    public function freeMemory(): void
    {
        // Nettoyage du contexte ORM
        $this->em->clear();
        
        // Force le garbage collector
        gc_collect_cycles();
        
        // Nettoyage des caches internes PHP
        if (function_exists('opcache_reset')) {
            // Ne pas faire en production !
            // opcache_reset();
        }
        
        // Libération du cache realpath
        clearstatcache();
    }

    /**
     * Surveillance continue de la mémoire
     */
    public function checkMemoryUsage(): array
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $usage = [
            'current_mb' => round($current / 1024 / 1024, 2),
            'peak_mb' => round($peak / 1024 / 1024, 2),
            'limit_mb' => round($limit / 1024 / 1024, 2),
            'percentage' => round(($current / $limit) * 100, 2)
        ];
        
        // Alerte si utilisation élevée
        if ($usage['percentage'] > 75) {
            error_log("High memory usage detected: {$usage['percentage']}%");
        }
        
        return $usage;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $lastChar = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        return match ($lastChar) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }
}
```

## Connection pooling

### Gestionnaire de connexions

```php
<?php

declare(strict_types=1);

namespace App\Service\Database;

use MulerTech\Database\Database\Interface\DriverInterface;

/**
 * Pool de connexions pour optimiser les performances
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ConnectionPool
{
    private array $readConnections = [];
    private array $writeConnections = [];
    private int $maxConnections;
    private int $currentConnections = 0;

    public function __construct(
        private readonly array $readConfig,
        private readonly array $writeConfig,
        int $maxConnections = 20
    ) {
        $this->maxConnections = $maxConnections;
    }

    /**
     * Obtient une connexion en lecture
     */
    public function getReadConnection(): DriverInterface
    {
        if (empty($this->readConnections)) {
            return $this->createConnection($this->readConfig);
        }
        
        // Round-robin sur les connexions disponibles
        $connection = array_shift($this->readConnections);
        $this->readConnections[] = $connection;
        
        return $connection;
    }

    /**
     * Obtient une connexion en écriture
     */
    public function getWriteConnection(): DriverInterface
    {
        if (empty($this->writeConnections)) {
            return $this->createConnection($this->writeConfig);
        }
        
        return $this->writeConnections[0]; // Master unique pour l'écriture
    }

    /**
     * Créé une nouvelle connexion
     */
    private function createConnection(array $config): DriverInterface
    {
        if ($this->currentConnections >= $this->maxConnections) {
            throw new \RuntimeException('Maximum connections reached');
        }
        
        $driver = new \MulerTech\Database\Database\MySQLDriver(
            $config['host'],
            $config['database'],
            $config['username'],
            $config['password'],
            $config['port'] ?? 3306,
            $config['options'] ?? []
        );
        
        $driver->connect();
        $this->currentConnections++;
        
        return $driver;
    }

    /**
     * Ferme toutes les connexions
     */
    public function closeAll(): void
    {
        foreach (array_merge($this->readConnections, $this->writeConnections) as $connection) {
            $connection->disconnect();
        }
        
        $this->readConnections = [];
        $this->writeConnections = [];
        $this->currentConnections = 0;
    }

    /**
     * Statistiques du pool
     */
    public function getStats(): array
    {
        return [
            'read_connections' => count($this->readConnections),
            'write_connections' => count($this->writeConnections),
            'total_connections' => $this->currentConnections,
            'max_connections' => $this->maxConnections,
            'usage_percentage' => round(($this->currentConnections / $this->maxConnections) * 100, 2)
        ];
    }
}
```

## Monitoring continu

### Dashboard de performance

```php
<?php

declare(strict_types=1);

namespace App\Service\Performance;

use MulerTech\Database\ORM\EmEngine;

/**
 * Dashboard de monitoring des performances
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PerformanceDashboard
{
    public function __construct(
        private readonly EmEngine $em,
        private readonly PerformanceProfiler $profiler
    ) {}

    /**
     * Métriques en temps réel
     */
    public function getRealTimeMetrics(): array
    {
        return [
            'timestamp' => time(),
            'database' => $this->getDatabaseMetrics(),
            'php' => $this->getPhpMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queries' => $this->getQueryMetrics()
        ];
    }

    private function getDatabaseMetrics(): array
    {
        $metrics = [];
        
        try {
            // Connexions actives
            $connections = $this->em->getConnection()->query("
                SELECT COUNT(*) as active_connections
                FROM information_schema.processlist 
                WHERE command != 'Sleep'
            ")->fetch();
            
            $metrics['active_connections'] = $connections['active_connections'];
            
            // Slow queries
            $slowQueries = $this->em->getConnection()->query("
                SHOW GLOBAL STATUS LIKE 'Slow_queries'
            ")->fetch();
            
            $metrics['slow_queries'] = $slowQueries['Value'];
            
            // QPS (Queries Per Second)
            $uptime = $this->em->getConnection()->query("
                SHOW GLOBAL STATUS LIKE 'Uptime'
            ")->fetch();
            
            $questions = $this->em->getConnection()->query("
                SHOW GLOBAL STATUS LIKE 'Questions'
            ")->fetch();
            
            $metrics['qps'] = round($questions['Value'] / $uptime['Value'], 2);
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }

    private function getPhpMetrics(): array
    {
        return [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'opcache_hit_rate' => $this->getOpcacheHitRate(),
            'opcache_memory_usage' => $this->getOpcacheMemoryUsage()
        ];
    }

    private function getCacheMetrics(): array
    {
        $metrics = [];
        
        try {
            // Métriques Redis si disponible
            if (extension_loaded('redis')) {
                $redis = new \Redis();
                $redis->connect('redis', 6379);
                
                $info = $redis->info();
                $metrics['redis'] = [
                    'memory_used_mb' => round($info['used_memory'] / 1024 / 1024, 2),
                    'hit_rate' => isset($info['keyspace_hits']) && isset($info['keyspace_misses']) 
                        ? round($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses']) * 100, 2)
                        : 0,
                    'connected_clients' => $info['connected_clients']
                ];
            }
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }

    private function getQueryMetrics(): array
    {
        $report = $this->profiler->generateReport();
        
        return [
            'total_queries' => $report['database']['total_queries'],
            'average_query_time_ms' => $report['database']['average_query_time_ms'],
            'slow_queries_count' => $report['database']['slow_queries_count']
        ];
    }

    private function getOpcacheHitRate(): float
    {
        if (!function_exists('opcache_get_status')) {
            return 0;
        }
        
        $status = opcache_get_status();
        if (!$status || !isset($status['opcache_statistics'])) {
            return 0;
        }
        
        $stats = $status['opcache_statistics'];
        $hits = $stats['hits'];
        $misses = $stats['misses'];
        
        return $hits + $misses > 0 ? round(($hits / ($hits + $misses)) * 100, 2) : 0;
    }

    private function getOpcacheMemoryUsage(): array
    {
        if (!function_exists('opcache_get_status')) {
            return [];
        }
        
        $status = opcache_get_status();
        if (!$status || !isset($status['memory_usage'])) {
            return [];
        }
        
        $memory = $status['memory_usage'];
        
        return [
            'used_mb' => round($memory['used_memory'] / 1024 / 1024, 2),
            'free_mb' => round($memory['free_memory'] / 1024 / 1024, 2),
            'wasted_mb' => round($memory['wasted_memory'] / 1024 / 1024, 2)
        ];
    }
}
```

---

Ce guide d'optimisation des performances fournit une approche complète pour maximiser les performances de MulerTech Database ORM en production, avec des techniques de profilage, d'optimisation et de monitoring continu.
