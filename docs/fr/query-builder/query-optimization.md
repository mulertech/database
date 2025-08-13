# Optimisation des Requêtes

## Introduction

L'optimisation des requêtes est cruciale pour maintenir de bonnes performances. Ce guide présente les techniques d'optimisation pour le Query Builder de MulerTech Database.

## Analyse de Performance

### Mesure du Temps d'Exécution

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PerformanceAnalyzer
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * @param callable(): mixed $queryCallback
     * @return array{
     *     execution_time: float,
     *     memory_usage: int,
     *     peak_memory: int
     * }
     */
    public function analyzeQuery(callable $queryCallback): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $result = $queryCallback();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        return [
            'execution_time' => ($endTime - $startTime) * 1000,
            'memory_usage' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}

// Utilisation
$analyzer = new PerformanceAnalyzer($queryBuilder);

$stats = $analyzer->analyzeQuery(function() use ($queryBuilder) {
    return $queryBuilder->select('*')
                       ->from('users')
                       ->where('status = ?', ['active'])
                       ->execute()
                       ->fetchAll();
});

echo "Temps d'exécution: {$stats['execution_time']}ms\n";
```

### Analyse EXPLAIN

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Query\Builder\RawQueryBuilder;

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
    public function explainQuery(string $sql): array
    {
        return $this->queryBuilder
            ->raw("EXPLAIN " . $sql)
            ->execute()
            ->fetchAll();
    }
    
    /**
     * @param array<string, mixed> $explainData
     * @return array<string>
     */
    public function analyzeExplain(array $explainData): array
    {
        $warnings = [];
        
        foreach ($explainData as $row) {
            if (isset($row['type']) && $row['type'] === 'ALL') {
                $warnings[] = 'Table scan complet détecté sur ' . $row['table'];
            }
            
            if (isset($row['Extra']) && strpos($row['Extra'], 'Using filesort') !== false) {
                $warnings[] = 'Tri externe utilisé';
            }
        }
        
        return $warnings;
    }
}

// Utilisation
$explainer = new QueryExplainer($queryBuilder);
$sql = "SELECT u.name FROM users u WHERE u.status = 'active'";
$explainData = $explainer->explainQuery($sql);
$warnings = $explainer->analyzeExplain($explainData);

foreach ($warnings as $warning) {
    echo "⚠️ $warning\n";
}
```

## Indexation et Stratégies

### Analyse d'Index

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

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
     *     name: string,
     *     columns: array<string>,
     *     unique: bool
     * }>
     */
    public function analyzeExistingIndexes(string $table): array
    {
        $indexes = $this->queryBuilder
            ->raw("SHOW INDEXES FROM {$table}")
            ->execute()
            ->fetchAll();
        
        $indexAnalysis = [];
        $grouped = [];
        
        foreach ($indexes as $index) {
            $grouped[$index['Key_name']][] = $index;
        }
        
        foreach ($grouped as $indexName => $columns) {
            $indexAnalysis[] = [
                'name' => $indexName,
                'columns' => array_column($columns, 'Column_name'),
                'unique' => $columns[0]['Non_unique'] == 0
            ];
        }
        
        return $indexAnalysis;
    }
}
```

### Recommandations d'Index

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class IndexOptimizer
{
    /**
     * @param array<string> $columns
     */
    public function createCompositeIndex(string $table, array $columns, bool $unique = false): string
    {
        $indexName = 'idx_' . $table . '_' . implode('_', $columns);
        $uniqueClause = $unique ? 'UNIQUE ' : '';
        $columnList = implode(', ', $columns);
        
        return "CREATE {$uniqueClause}INDEX {$indexName} ON {$table} ({$columnList})";
    }
}
```

## Optimisation des Requêtes SELECT

### Bonnes Pratiques

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Query\Builder\SelectBuilder;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryOptimizer
{
    public function optimizeSelect(QueryBuilder $queryBuilder): SelectBuilder
    {
        // Spécifier les colonnes au lieu de SELECT *
        return $queryBuilder->select('id', 'name', 'email')
            ->from('users')
            ->where('status = ?', ['active'])
            ->orderBy('created_at DESC')
            ->limit(100);
    }
    
    public function avoidFunctionsInWhere(QueryBuilder $queryBuilder): SelectBuilder
    {
        // ✅ Utiliser des plages au lieu de fonctions
        return $queryBuilder->select('*')
            ->from('orders')
            ->where('created_at >= ? AND created_at < ?', [
                '2024-01-01 00:00:00',
                '2025-01-01 00:00:00'
            ]);
    }
    
    public function optimizeLikeQueries(QueryBuilder $queryBuilder, string $searchTerm): SelectBuilder
    {
        // Recherche préfixée (plus efficace)
        if (strlen($searchTerm) >= 3) {
            return $queryBuilder->select('*')
                ->from('products')
                ->where('name LIKE ?', [$searchTerm . '%']);
        }
        
        return $queryBuilder->select('*')->from('products');
    }
}
```

### Pagination Efficace

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

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
     * @return array<array<string, mixed>>
     */
    public function paginate(string $table, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        return $this->queryBuilder
            ->select('*')
            ->from($table)
            ->orderBy('id ASC')
            ->limit($perPage)
            ->offset($offset)
            ->execute()
            ->fetchAll();
    }
    
    /**
     * Pagination par curseur pour de gros datasets
     * @return array<array<string, mixed>>
     */
    public function cursorPagination(string $table, ?int $lastId, int $perPage): array
    {
        $qb = $this->queryBuilder->select('*')->from($table);
        
        if ($lastId !== null) {
            $qb->where('id > ?', [$lastId]);
        }
        
        return $qb->orderBy('id ASC')
                 ->limit($perPage)
                 ->execute()
                 ->fetchAll();
    }
}
```

## Optimisation des Jointures

### Bonnes Pratiques

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class JoinOptimizer
{
    public function optimizedJoin(QueryBuilder $queryBuilder): \MulerTech\Database\Query\Builder\SelectBuilder
    {
        // Jointures avec conditions spécifiques
        return $queryBuilder
            ->select('u.name', 'p.title')
            ->from('users', 'u')
            ->innerJoin('posts', 'p', 'u.id = p.user_id')
            ->where('u.status = ?', ['active'])
            ->where('p.published = ?', [1]);
    }
}
```

### Jointures vs Sous-requêtes

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

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
    
    public function withJoin(): array
    {
        return $this->queryBuilder
            ->select('DISTINCT u.*')
            ->from('users', 'u')
            ->innerJoin('orders', 'o', 'u.id = o.user_id')
            ->where('o.total > ?', [1000])
            ->execute()
            ->fetchAll();
    }
    
    public function withExists(): array
    {
        return $this->queryBuilder
            ->raw('SELECT * FROM users u WHERE EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id AND o.total > ?)', [1000])
            ->execute()
            ->fetchAll();
    }
}
```

## Gestion de la Mémoire

### Traitement par Lots

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

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
        $batchSize = 1000;
        $offset = 0;
        
        do {
            $batch = $this->queryBuilder
                ->select('*')
                ->from('large_table')
                ->orderBy('id ASC')
                ->limit($batchSize)
                ->offset($offset)
                ->execute()
                ->fetchAll();
            
            if (empty($batch)) {
                break;
            }
            
            $processor($batch);
            $offset += $batchSize;
            
            unset($batch);
            gc_collect_cycles();
            
        } while (true);
    }
}
```

## Cache et Mise en Cache

### Exemple de Cache Simple

```php
<?php
declare(strict_types=1);

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryCache
{
    /**
     * @var array<string, array{data: mixed, expires: int}>
     */
    private array $cache = [];
    private int $defaultTtl = 3600;
    
    /**
     * @param callable(): mixed $queryCallback
     * @return mixed
     */
    public function cachedQuery(string $key, callable $queryCallback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $now = time();
        
        if (isset($this->cache[$key]) && $this->cache[$key]['expires'] > $now) {
            return $this->cache[$key]['data'];
        }
        
        $result = $queryCallback();
        
        $this->cache[$key] = [
            'data' => $result,
            'expires' => $now + $ttl
        ];
        
        return $result;
    }
}
```

## Monitoring et Profiling

### Monitoring Simple

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SlowQueryMonitor
{
    private QueryBuilder $queryBuilder;
    
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
            ->raw("SET GLOBAL long_query_time = 1.0")
            ->execute();
    }
    
    /**
     * @return array<array<string, mixed>>
     */
    public function getSlowQueries(): array
    {
        return $this->queryBuilder
            ->raw("SHOW PROCESSLIST")
            ->execute()
            ->fetchAll();
    }
}
```

## Bonnes Pratiques

### Recommandations

- Spécifier les colonnes au lieu d'utiliser SELECT *
- Utiliser LIMIT avec ORDER BY
- Éviter les fonctions dans les clauses WHERE
- Créer des index appropriés pour les colonnes fréquemment utilisées
- Utiliser la pagination par curseur pour de gros datasets
- Monitorer les requêtes lentes

```php
<?php
// ❌ À éviter
$results = $queryBuilder->select('*')
    ->from('users')
    ->where('YEAR(created_at) = 2024')
    ->orderBy('name')
    ->execute()
    ->fetchAll();

// ✅ Recommandé
$results = $queryBuilder->select('id', 'name', 'email')
    ->from('users')
    ->where('created_at >= ? AND created_at < ?', ['2024-01-01', '2025-01-01'])
    ->orderBy('name')
    ->limit(100)
    ->execute()
    ->fetchAll();
```

## Outils de Debug

### Debug Simple

```php
<?php
declare(strict_types=1);

use MulerTech\Database\Query\Builder\QueryBuilder;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryDebugger
{
    private QueryBuilder $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    public function debugQuery(string $sql): void
    {
        echo "SQL: " . $sql . "\n";
        
        try {
            $explain = $this->queryBuilder
                ->raw("EXPLAIN " . $sql)
                ->execute()
                ->fetchAll();
            
            foreach ($explain as $row) {
                echo "Table: {$row['table']}, Type: {$row['type']}\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
```

## Prochaines Étapes

- [Requêtes de Base](basic-queries.md)
- [Requêtes Avancées](advanced-queries.md)
