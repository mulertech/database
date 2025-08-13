# Optimisation des Requêtes

## Table des Matières
- [Introduction](#introduction)
- [Analyse de Performance](#analyse-de-performance)
- [Indexation et Stratégies](#indexation-et-stratégies)
- [Optimisation des Requêtes SELECT](#optimisation-des-requêtes-select)
- [Optimisation des Jointures](#optimisation-des-jointures)
- [Gestion de la Mémoire](#gestion-de-la-mémoire)
- [Cache et Mise en Cache](#cache-et-mise-en-cache)
- [Monitoring et Profiling](#monitoring-et-profiling)
- [Bonnes Pratiques](#bonnes-pratiques)
- [Outils de Debug](#outils-de-debug)

## Introduction

L'optimisation des requêtes est cruciale pour maintenir de bonnes performances dans une application utilisant une base de données. Ce guide couvre les techniques d'optimisation, les outils de monitoring et les bonnes pratiques pour le Query Builder de MulerTech Database.

## Analyse de Performance

### Mesure du Temps d'Exécution

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Query\Performance\QueryProfiler;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PerformanceAnalyzer
{
    private QueryBuilder $queryBuilder;
    private QueryProfiler $profiler;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
        $this->profiler = new QueryProfiler();
    }
    
    /**
     * @param callable(QueryBuilder): mixed $queryCallback
     * @return array{
     *     execution_time: float,
     *     memory_usage: int,
     *     peak_memory: int,
     *     result_count: int,
     *     sql: string,
     *     parameters: array<string, mixed>
     * }
     */
    public function analyzeQuery(callable $queryCallback): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Exécution de la requête
        $result = $queryCallback($this->queryBuilder);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        return [
            'execution_time' => ($endTime - $startTime) * 1000, // en ms
            'memory_usage' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'result_count' => is_array($result) ? count($result) : 1,
            'sql' => $this->queryBuilder->getLastExecutedSQL(),
            'parameters' => $this->queryBuilder->getLastParameters()
        ];
    }
}

// Utilisation
$analyzer = new PerformanceAnalyzer($queryBuilder);

$stats = $analyzer->analyzeQuery(function($qb) {
    return $qb->select(['*'])
             ->from('users')
             ->where('status = ?', ['active'])
             ->execute()
             ->fetchAll();
});

echo "Temps d'exécution: {$stats['execution_time']}ms\n";
echo "Mémoire utilisée: " . number_format($stats['memory_usage'] / 1024) . "KB\n";
```

### Analyse EXPLAIN

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryExplainer
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * @return array<string, mixed>
     */
    public function explainQuery(QueryBuilder $qb): array
    {
        $sql = $qb->getSQL();
        $params = $qb->getParameters();
        
        // MySQL EXPLAIN
        $explainResult = $this->queryBuilder
            ->raw("EXPLAIN FORMAT=JSON " . $sql, $params)
            ->fetch();
        
        return json_decode($explainResult['EXPLAIN'], true);
    }
    
    /**
     * @param array<string, mixed> $explainData
     * @return array{
     *     warnings: array<string>,
     *     suggestions: array<string>,
     *     cost: float,
     *     examined_rows: int
     * }
     */
    public function analyzeExplain(array $explainData): array
    {
        $analysis = [
            'warnings' => [],
            'suggestions' => [],
            'cost' => 0,
            'examined_rows' => 0
        ];
        
        $queryBlock = $explainData['query_block'] ?? [];
        
        // Analyse du coût
        if (isset($queryBlock['cost_info']['query_cost'])) {
            $analysis['cost'] = (float)$queryBlock['cost_info']['query_cost'];
        }
        
        // Détection des problèmes
        if (isset($queryBlock['table'])) {
            $table = $queryBlock['table'];
            
            // Table scan complet
            if ($table['access_type'] === 'ALL') {
                $analysis['warnings'][] = 'Table scan complet détecté';
                $analysis['suggestions'][] = 'Ajouter un index approprié';
            }
            
            // Filesort
            if (isset($table['using_filesort']) && $table['using_filesort']) {
                $analysis['warnings'][] = 'Tri externe (filesort) utilisé';
                $analysis['suggestions'][] = 'Considérer un index composite pour le tri';
            }
            
            // Temporary table
            if (isset($table['using_temporary_table']) && $table['using_temporary_table']) {
                $analysis['warnings'][] = 'Table temporaire utilisée';
                $analysis['suggestions'][] = 'Optimiser la requête pour éviter la table temporaire';
            }
        }
        
        return $analysis;
    }
}

// Utilisation
$explainer = new QueryExplainer($queryBuilder);

$qb = $queryBuilder
    ->select(['u.name', 'COUNT(o.id) as order_count'])
    ->from('users', 'u')
    ->leftJoin('u', 'orders', 'o', 'u.id = o.user_id')
    ->groupBy('u.id')
    ->orderBy('order_count', 'DESC');

$explainData = $explainer->explainQuery($qb);
$analysis = $explainer->analyzeExplain($explainData);

foreach ($analysis['warnings'] as $warning) {
    echo "⚠️ $warning\n";
}

foreach ($analysis['suggestions'] as $suggestion) {
    echo "💡 $suggestion\n";
}
```

## Indexation et Stratégies

### Détection d'Index Manquants

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class IndexAnalyzer
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * @return array<array{
     *     type: string,
     *     table: string,
     *     columns: array<string>,
     *     reason: string,
     *     priority: string
     * }>
     */
    public function suggestIndexes(string $table): array
    {
        // Analyse des requêtes lentes
        $slowQueries = $this->queryBuilder
            ->raw("
                SELECT 
                    sql_text,
                    exec_count,
                    avg_timer_wait/1000000000 as avg_exec_time_sec,
                    sum_rows_examined/exec_count as avg_rows_examined
                FROM performance_schema.events_statements_summary_by_digest
                WHERE digest_text LIKE CONCAT('%', ?, '%')
                AND avg_timer_wait/1000000000 > 0.1
                ORDER BY avg_timer_wait DESC
                LIMIT 10
            ", [$table])
            ->fetchAll();
        
        $suggestions = [];
        
        foreach ($slowQueries as $query) {
            // Analyse simple des patterns
            if (preg_match('/WHERE\s+(\w+)\s*=/', $query['sql_text'], $matches)) {
                $column = $matches[1];
                $suggestions[] = [
                    'type' => 'single_column',
                    'table' => $table,
                    'columns' => [$column],
                    'reason' => 'Condition WHERE fréquente',
                    'priority' => 'high'
                ];
            }
            
            if (preg_match('/ORDER BY\s+(\w+)/', $query['sql_text'], $matches)) {
                $column = $matches[1];
                $suggestions[] = [
                    'type' => 'single_column',
                    'table' => $table,
                    'columns' => [$column],
                    'reason' => 'Tri fréquent',
                    'priority' => 'medium'
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * @return array<array{
     *     name: string,
     *     type: string,
     *     unique: bool,
     *     columns: array<string>,
     *     cardinality: int,
     *     issues: array<string>
     * }>
     */
    public function analyzeExistingIndexes(string $table): array
    {
        $indexes = $this->queryBuilder
            ->raw("SHOW INDEXES FROM {$table}")
            ->fetchAll();
        
        $indexAnalysis = [];
        $grouped = [];
        
        // Grouper par nom d'index
        foreach ($indexes as $index) {
            $grouped[$index['Key_name']][] = $index;
        }
        
        foreach ($grouped as $indexName => $columns) {
            $analysis = [
                'name' => $indexName,
                'type' => $columns[0]['Index_type'],
                'unique' => $columns[0]['Non_unique'] == 0,
                'columns' => array_column($columns, 'Column_name'),
                'cardinality' => array_sum(array_column($columns, 'Cardinality')),
                'issues' => []
            ];
            
            // Détection de problèmes
            if ($analysis['cardinality'] < 100 && !$analysis['unique']) {
                $analysis['issues'][] = 'Faible cardinalité - index peu efficace';
            }
            
            if (count($analysis['columns']) > 5) {
                $analysis['issues'][] = 'Index composite très large';
            }
            
            $indexAnalysis[] = $analysis;
        }
        
        return $indexAnalysis;
    }
}
```

### Stratégies d'Indexation

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class IndexOptimizer
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * @param array<array{type: string, column: string}> $commonConditions
     * @return array<array{sql: string, type: string, benefit: string}>
     */
    public function optimizeForSelectQueries(string $table, array $commonConditions): array
    {
        $recommendations = [];
        
        // Index pour conditions WHERE
        foreach ($commonConditions as $condition) {
            if ($condition['type'] === 'equality') {
                $recommendations[] = [
                    'sql' => "CREATE INDEX idx_{$table}_{$condition['column']} ON {$table} ({$condition['column']})",
                    'type' => 'equality_index',
                    'benefit' => 'Améliore les requêtes avec WHERE ' . $condition['column'] . ' = value'
                ];
            }
            
            if ($condition['type'] === 'range') {
                $recommendations[] = [
                    'sql' => "CREATE INDEX idx_{$table}_{$condition['column']} ON {$table} ({$condition['column']})",
                    'type' => 'range_index',
                    'benefit' => 'Améliore les requêtes avec des plages sur ' . $condition['column']
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * @param array<string> $columns
     * @param array{name?: string, unique?: bool} $options
     */
    public function createCompositeIndex(string $table, array $columns, array $options = []): string
    {
        $indexName = $options['name'] ?? 'idx_' . $table . '_' . implode('_', $columns);
        $unique = $options['unique'] ?? false;
        
        // Ordre optimal des colonnes dans l'index composite
        $orderedColumns = $this->optimizeColumnOrder($table, $columns);
        
        $uniqueClause = $unique ? 'UNIQUE ' : '';
        $columnList = implode(', ', $orderedColumns);
        
        return "CREATE {$uniqueClause}INDEX {$indexName} ON {$table} ({$columnList})";
    }
    
    /**
     * @param array<string> $columns
     * @return array<string>
     */
    private function optimizeColumnOrder(string $table, array $columns): array
    {
        $columnStats = [];
        
        foreach ($columns as $column) {
            $stats = $this->queryBuilder
                ->raw("
                    SELECT 
                        COUNT(DISTINCT {$column}) as cardinality,
                        COUNT(*) as total_rows,
                        COUNT(DISTINCT {$column}) / COUNT(*) as selectivity
                    FROM {$table}
                ")
                ->fetch();
            
            $columnStats[$column] = $stats;
        }
        
        // Trier par sélectivité décroissante
        uasort($columnStats, function($a, $b) {
            return $b['selectivity'] <=> $a['selectivity'];
        });
        
        return array_keys($columnStats);
    }
}
```

## Optimisation des Requêtes SELECT

### Optimisation des Conditions WHERE

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class WhereOptimizer
{
    public function optimizeConditions(QueryBuilder $qb): QueryBuilder
    {
        // Réorganiser les conditions par sélectivité
        // Les conditions les plus sélectives en premier
        
        return $qb
            // Conditions d'égalité sur des colonnes indexées d'abord
            ->where('indexed_status = ?', ['active'])
            // Puis conditions de plage
            ->andWhere('created_at BETWEEN ? AND ?', ['2024-01-01', '2024-12-31'])
            // Conditions sur des chaînes en dernier
            ->andWhere('description LIKE ?', ['%keyword%']);
    }
    
    public function avoidFunctionsInWhere(QueryBuilder $qb): QueryBuilder
    {
        // ❌ Éviter les fonctions dans WHERE
        // ->where('YEAR(created_at) = ?', [2024])
        
        // ✅ Utiliser des plages
        return $qb->where('created_at >= ? AND created_at < ?', [
            '2024-01-01 00:00:00',
            '2025-01-01 00:00:00'
        ]);
    }
    
    public function optimizeLikeQueries(QueryBuilder $qb, string $searchTerm): QueryBuilder
    {
        // Pour les recherches préfixées (plus efficaces)
        if (strlen($searchTerm) >= 3) {
            return $qb->where('name LIKE ?', [$searchTerm . '%']);
        }
        
        // Pour la recherche full-text
        return $qb->where('MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)', [$searchTerm]);
    }
}
```

### Limitation et Pagination Efficace

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PaginationOptimizer
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * @param array<string, mixed> $conditions
     * @return array<array<string, mixed>>
     */
    public function efficientPagination(string $table, int $page, int $perPage, array $conditions = []): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Pour de gros OFFSET, utiliser une technique de curseur
        if ($offset > 10000) {
            return $this->cursorBasedPagination($table, $page, $perPage, $conditions);
        }
        
        // Pagination normale pour de petits offsets
        $qb = $this->queryBuilder
            ->select(['*'])
            ->from($table);
        
        foreach ($conditions as $condition => $value) {
            $qb->andWhere($condition, $value);
        }
        
        return $qb->orderBy('id', 'ASC')
                 ->limit($perPage)
                 ->offset($offset)
                 ->execute()
                 ->fetchAll();
    }
    
    /**
     * @param array<string, mixed> $conditions
     * @return array<array<string, mixed>>
     */
    private function cursorBasedPagination(string $table, int $page, int $perPage, array $conditions): array
    {
        // Utiliser un curseur basé sur l'ID pour éviter les gros OFFSET
        $lastId = ($page - 1) * $perPage;
        
        $qb = $this->queryBuilder
            ->select(['*'])
            ->from($table)
            ->where('id > ?', [$lastId]);
        
        foreach ($conditions as $condition => $value) {
            $qb->andWhere($condition, $value);
        }
        
        return $qb->orderBy('id', 'ASC')
                 ->limit($perPage)
                 ->execute()
                 ->fetchAll();
    }
    
    /**
     * @param array<string, mixed> $conditions
     */
    public function countOptimization(string $table, array $conditions = []): int
    {
        // Utiliser une approximation pour de très grandes tables
        $estimatedRows = $this->queryBuilder
            ->raw("
                SELECT table_rows 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
            ", [$table])
            ->fetch()['table_rows'] ?? 0;
        
        // Si la table est très grande et qu'il n'y a pas de conditions complexes
        if ($estimatedRows > 1000000 && empty($conditions)) {
            return $estimatedRows;
        }
        
        // Sinon, compter normalement
        $qb = $this->queryBuilder
            ->select(['COUNT(*) as total'])
            ->from($table);
        
        foreach ($conditions as $condition => $value) {
            $qb->andWhere($condition, $value);
        }
        
        return (int)$qb->execute()->fetch()['total'];
    }
}
```

## Optimisation des Jointures

### Stratégies de Jointure

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class JoinOptimizer
{
    public function optimizeJoinOrder(QueryBuilder $qb): QueryBuilder
    {
        // Ordre recommandé:
        // 1. Table avec le moins de lignes d'abord
        // 2. Tables avec des conditions restrictives
        // 3. Utiliser STRAIGHT_JOIN si nécessaire pour forcer l'ordre
        
        return $qb
            ->select(['u.name', 'p.title', 'c.name as category'])
            ->from('users', 'u')  // Table la plus restrictive
            ->innerJoin('u', 'posts', 'p', 'u.id = p.user_id AND p.status = "published"')
            ->innerJoin('p', 'categories', 'c', 'p.category_id = c.id');
    }
    
    public function avoidCartesianProducts(QueryBuilder $qb): QueryBuilder
    {
        // ❌ Éviter les jointures sans conditions appropriées
        // $qb->from('users', 'u')->innerJoin('u', 'posts', 'p', '1=1')
        
        // ✅ Toujours spécifier des conditions de jointure précises
        return $qb
            ->from('users', 'u')
            ->innerJoin('u', 'posts', 'p', 'u.id = p.user_id')
            ->where('u.status = ?', ['active']);
    }
    
    public function pushDownConditions(QueryBuilder $qb): QueryBuilder
    {
        // Pousser les conditions WHERE vers les tables appropriées
        return $qb
            ->select(['u.name', 'p.title'])
            ->from('users', 'u')
            ->innerJoin('u', 'posts', 'p', 'u.id = p.user_id AND p.published_at >= "2024-01-01"')
            ->where('u.created_at >= ?', ['2023-01-01']);
    }
}
```

### Jointures vs Sous-requêtes

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class JoinVsSubqueryOptimizer
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * @return array{
     *     join: array{time: float, count: int},
     *     exists: array{time: float, count: int},
     *     recommendation: string
     * }
     */
    public function comparePerformance(int $userId): array
    {
        // Approche avec JOIN
        $startTime = microtime(true);
        
        $joinResult = $this->queryBuilder
            ->select(['DISTINCT u.*'])
            ->from('users', 'u')
            ->innerJoin('u', 'orders', 'o', 'u.id = o.user_id')
            ->where('o.total > ?', [1000])
            ->execute()
            ->fetchAll();
        
        $joinTime = microtime(true) - $startTime;
        
        // Approche avec EXISTS
        $startTime = microtime(true);
        
        $existsResult = $this->queryBuilder
            ->select(['*'])
            ->from('users', 'u')
            ->where('EXISTS (
                SELECT 1 FROM orders o 
                WHERE o.user_id = u.id AND o.total > ?
            )', [1000])
            ->execute()
            ->fetchAll();
        
        $existsTime = microtime(true) - $startTime;
        
        return [
            'join' => ['time' => $joinTime, 'count' => count($joinResult)],
            'exists' => ['time' => $existsTime, 'count' => count($existsResult)],
            'recommendation' => $joinTime < $existsTime ? 'JOIN' : 'EXISTS'
        ];
    }
}
```

## Gestion de la Mémoire

### Lecture en Streaming

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MemoryOptimizer
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * @param callable(array<array<string, mixed>>): void $processor
     */
    public function processLargeDataset(callable $processor): void
    {
        // Traitement par chunks pour éviter l'épuisement mémoire
        $batchSize = 1000;
        $offset = 0;
        
        do {
            $batch = $this->queryBuilder
                ->select(['*'])
                ->from('large_table')
                ->orderBy('id', 'ASC')
                ->limit($batchSize)
                ->offset($offset)
                ->execute()
                ->fetchAll();
            
            if (empty($batch)) {
                break;
            }
            
            // Traitement du batch
            $processor($batch);
            
            $offset += $batchSize;
            
            // Libération de mémoire
            unset($batch);
            gc_collect_cycles();
            
        } while (true);
    }
    
    /**
     * @param array<string, mixed> $params
     * @return \Generator<array<string, mixed>>
     */
    public function streamResults(string $query, array $params = []): \Generator
    {
        $statement = $this->queryBuilder
            ->raw($query, $params)
            ->getStatement();
        
        while ($row = $statement->fetch()) {
            yield $row;
        }
    }
    
    public function memoryEfficientExport(string $filename): void
    {
        $file = fopen($filename, 'w');
        
        // Écriture de l'en-tête CSV
        fputcsv($file, ['id', 'name', 'email', 'created_at']);
        
        // Streaming des données
        foreach ($this->streamResults('SELECT * FROM users ORDER BY id') as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
    }
}
```

## Cache et Mise en Cache

### Cache de Requêtes

```php
<?php
declare(strict_types=1);

use Psr\Cache\CacheItemPoolInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryCache
{
    private QueryBuilder $queryBuilder;
    private CacheItemPoolInterface $cache;
    private int $defaultTtl = 3600; // 1 heure
    
    public function __construct(QueryBuilder $queryBuilder, CacheItemPoolInterface $cache)
    {
        $this->queryBuilder = $queryBuilder;
        $this->cache = $cache;
    }
    
    /**
     * @param callable(QueryBuilder): array<array<string, mixed>> $queryCallback
     * @return array<array<string, mixed>>
     */
    public function cachedQuery(string $key, callable $queryCallback, ?int $ttl = null): array
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $cacheItem = $this->cache->getItem($key);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        // Exécution de la requête
        $result = $queryCallback($this->queryBuilder);
        
        // Mise en cache
        $cacheItem->set($result);
        $cacheItem->expiresAfter($ttl);
        $this->cache->save($cacheItem);
        
        return $result;
    }
    
    /**
     * @param array<string> $patterns
     */
    public function invalidatePattern(string $pattern): void
    {
        // Invalidation de cache par pattern
        $this->cache->deleteItems($this->findKeysByPattern($pattern));
    }
    
    /**
     * @return array<string>
     */
    private function findKeysByPattern(string $pattern): array
    {
        // Implementation dépendante du cache utilisé
        // Ex: Redis avec SCAN, APCu avec APCUIterator
        return [];
    }
}

// Utilisation
$cache = new QueryCache($queryBuilder, $cachePool);

$users = $cache->cachedQuery(
    'users_active_' . date('Y-m-d-H'), 
    function($qb) {
        return $qb->select(['*'])
                 ->from('users')
                 ->where('status = ?', ['active'])
                 ->execute()
                 ->fetchAll();
    },
    1800 // 30 minutes
);
```

## Monitoring et Profiling

### Monitoring des Requêtes Lentes

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SlowQueryMonitor
{
    private QueryBuilder $queryBuilder;
    private float $slowQueryThreshold = 1.0; // 1 seconde
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    public function enableSlowQueryLogging(): void
    {
        $this->queryBuilder
            ->raw("SET GLOBAL slow_query_log = 'ON'")
            ->execute();
        
        $this->queryBuilder
            ->raw("SET GLOBAL long_query_time = ?", [$this->slowQueryThreshold])
            ->execute();
    }
    
    /**
     * @return array<array{
     *     digest_text: string,
     *     exec_count: int,
     *     avg_exec_time_sec: float,
     *     max_exec_time_sec: float,
     *     avg_rows_examined: float,
     *     avg_rows_sent: float,
     *     first_seen: string,
     *     last_seen: string
     * }>
     */
    public function getSlowQueries(int $limit = 50): array
    {
        return $this->queryBuilder
            ->raw("
                SELECT 
                    digest_text,
                    count_star as exec_count,
                    avg_timer_wait/1000000000 as avg_exec_time_sec,
                    max_timer_wait/1000000000 as max_exec_time_sec,
                    sum_rows_examined/count_star as avg_rows_examined,
                    sum_rows_sent/count_star as avg_rows_sent,
                    first_seen,
                    last_seen
                FROM performance_schema.events_statements_summary_by_digest
                WHERE avg_timer_wait/1000000000 > ?
                ORDER BY avg_timer_wait DESC
                LIMIT ?
            ", [$this->slowQueryThreshold, $limit])
            ->fetchAll();
    }
    
    /**
     * @return array<array{
     *     digest_text: string,
     *     exec_count: int,
     *     avg_exec_time_sec: float,
     *     max_exec_time_sec: float,
     *     avg_rows_examined: float,
     *     avg_rows_sent: float,
     *     first_seen: string,
     *     last_seen: string,
     *     issues: array<string>
     * }>
     */
    public function analyzeSlowQueries(): array
    {
        $slowQueries = $this->getSlowQueries();
        $analysis = [];
        
        foreach ($slowQueries as $query) {
            $issues = [];
            
            // Détection de patterns problématiques
            if ($query['avg_rows_examined'] > 10000) {
                $issues[] = 'Examine trop de lignes';
            }
            
            if ($query['avg_rows_examined'] / max($query['avg_rows_sent'], 1) > 100) {
                $issues[] = 'Ratio examen/retour trop élevé';
            }
            
            if (strpos($query['digest_text'], 'SELECT *') !== false) {
                $issues[] = 'Utilise SELECT *';
            }
            
            if (strpos($query['digest_text'], 'ORDER BY') !== false && 
                strpos($query['digest_text'], 'LIMIT') === false) {
                $issues[] = 'ORDER BY sans LIMIT';
            }
            
            $analysis[] = array_merge($query, ['issues' => $issues]);
        }
        
        return $analysis;
    }
}
```

## Bonnes Pratiques

### Checklist d'Optimisation

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class OptimizationChecklist
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * @return array{
     *     issues: array<string>,
     *     suggestions: array<string>,
     *     score: int
     * }
     */
    public function auditQuery(QueryBuilder $qb): array
    {
        $sql = $qb->getSQL();
        $issues = [];
        $suggestions = [];
        
        // 1. Vérifier SELECT *
        if (strpos($sql, 'SELECT *') !== false) {
            $issues[] = 'Utilise SELECT * au lieu de colonnes spécifiques';
            $suggestions[] = 'Spécifier uniquement les colonnes nécessaires';
        }
        
        // 2. Vérifier LIMIT
        if (strpos($sql, 'ORDER BY') !== false && strpos($sql, 'LIMIT') === false) {
            $issues[] = 'ORDER BY sans LIMIT peut être coûteux';
            $suggestions[] = 'Ajouter LIMIT si approprié';
        }
        
        // 3. Vérifier les fonctions dans WHERE
        if (preg_match('/WHERE.*\w+\(/', $sql)) {
            $issues[] = 'Utilise des fonctions dans la clause WHERE';
            $suggestions[] = 'Éviter les fonctions dans WHERE pour utiliser les index';
        }
        
        // 4. Vérifier les LIKE avec wildcard au début
        if (strpos($sql, "LIKE '%") !== false) {
            $issues[] = 'LIKE avec wildcard au début empêche l\'utilisation d\'index';
            $suggestions[] = 'Utiliser full-text search ou repositionner le wildcard';
        }
        
        // 5. Vérifier OR dans WHERE
        if (preg_match('/WHERE.*OR/', $sql)) {
            $issues[] = 'Utilise OR dans WHERE (peut empêcher l\'utilisation d\'index)';
            $suggestions[] = 'Considérer UNION ou restructurer la requête';
        }
        
        return [
            'issues' => $issues,
            'suggestions' => $suggestions,
            'score' => max(0, 100 - (count($issues) * 20))
        ];
    }
    
    public function generateOptimizedVersion(QueryBuilder $qb): string
    {
        $sql = $qb->getSQL();
        
        // Remplacements automatiques simples
        $optimized = $sql;
        
        // Remplacer SELECT * par des colonnes communes
        $optimized = preg_replace(
            '/SELECT \*/',
            'SELECT id, name, created_at, updated_at',
            $optimized
        );
        
        // Ajouter LIMIT si ORDER BY sans LIMIT
        if (strpos($optimized, 'ORDER BY') !== false && strpos($optimized, 'LIMIT') === false) {
            $optimized .= ' LIMIT 100';
        }
        
        return $optimized;
    }
}
```

## Outils de Debug

### Query Debugger

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryDebugger
{
    private QueryBuilder $queryBuilder;
    private bool $debugMode = false;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    public function enableDebug(): void
    {
        $this->debugMode = true;
    }
    
    /**
     * @return array{
     *     sql: string,
     *     parameters: array<string, mixed>,
     *     formatted_sql: string,
     *     explain: array<string, mixed>,
     *     estimated_cost: float
     * }
     */
    public function debugQuery(QueryBuilder $qb): array
    {
        if (!$this->debugMode) {
            return [];
        }
        
        $sql = $qb->getSQL();
        $params = $qb->getParameters();
        
        $debug = [
            'sql' => $sql,
            'parameters' => $params,
            'formatted_sql' => $this->formatSQL($sql),
            'explain' => $this->explainQuery($sql, $params),
            'estimated_cost' => $this->estimateCost($sql, $params)
        ];
        
        if ($this->debugMode) {
            $this->outputDebugInfo($debug);
        }
        
        return $debug;
    }
    
    private function formatSQL(string $sql): string
    {
        // Formatage simple du SQL pour la lisibilité
        $formatted = preg_replace('/\s+/', ' ', $sql);
        $formatted = str_replace([' FROM ', ' WHERE ', ' JOIN ', ' ORDER BY ', ' GROUP BY '], 
                                ["\nFROM ", "\nWHERE ", "\nJOIN ", "\nORDER BY ", "\nGROUP BY "], 
                                $formatted);
        
        return $formatted;
    }
    
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function explainQuery(string $sql, array $params): array
    {
        try {
            return $this->queryBuilder
                ->raw("EXPLAIN " . $sql, $params)
                ->fetchAll();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * @param array<string, mixed> $params
     */
    private function estimateCost(string $sql, array $params): float
    {
        try {
            $explain = $this->queryBuilder
                ->raw("EXPLAIN FORMAT=JSON " . $sql, $params)
                ->fetch();
            
            $data = json_decode($explain['EXPLAIN'], true);
            return (float)($data['query_block']['cost_info']['query_cost'] ?? 0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * @param array<string, mixed> $debug
     */
    private function outputDebugInfo(array $debug): void
    {
        echo "\n=== QUERY DEBUG ===\n";
        echo "SQL: " . $debug['formatted_sql'] . "\n";
        echo "Parameters: " . json_encode($debug['parameters']) . "\n";
        echo "Estimated Cost: " . $debug['estimated_cost'] . "\n";
        
        if (!empty($debug['explain']) && !isset($debug['explain']['error'])) {
            echo "EXPLAIN:\n";
            foreach ($debug['explain'] as $row) {
                echo "  Type: {$row['select_type']}, Table: {$row['table']}, Key: {$row['key']}\n";
            }
        }
        echo "==================\n\n";
    }
}
```

## Prochaines Étapes

- [Requêtes de Base](basic-queries.md) - Retour aux fondamentaux
- [Requêtes Avancées](advanced-queries.md) - Jointures et sous-requêtes
- [Requêtes SQL Brutes](raw-queries.md) - SQL natif et procédures stockées
