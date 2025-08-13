# Optimisations et Performance

## Table des Matières
- [Stratégies d'Optimisation](#stratégies-doptimisation)
- [Optimisation des Requêtes](#optimisation-des-requêtes)
- [Lazy Loading vs Eager Loading](#lazy-loading-vs-eager-loading)
- [Batch Operations](#batch-operations)
- [Profiling et Monitoring](#profiling-et-monitoring)
- [Optimisation Mémoire](#optimisation-mémoire)
- [Configuration Performance](#configuration-performance)
- [Bonnes Pratiques](#bonnes-pratiques)

## Stratégies d'Optimisation

### Vue d'ensemble

```php
<?php
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Core\Performance\PerformanceMonitor;

// Configuration optimisée pour la performance
$config = [
    'cache' => [
        'enabled' => true,
        'driver' => 'redis',
        'ttl' => 3600
    ],
    'lazy_loading' => true,
    'batch_size' => 1000,
    'query_cache' => true,
    'metadata_cache' => true
];

$emEngine = new EmEngine($driver, null, $config);
$monitor = new PerformanceMonitor();
$emEngine->setPerformanceMonitor($monitor);
```

### Niveaux d'Optimisation

```php
<?php
enum OptimizationLevel: string
{
    case DEVELOPMENT = 'dev';
    case TESTING = 'test';
    case PRODUCTION = 'prod';
    case HIGH_PERFORMANCE = 'hp';
}

class PerformanceConfigManager
{
    private array $configs = [];

    public function __construct()
    {
        $this->setupConfigs();
    }

    private function setupConfigs(): void
    {
        $this->configs[OptimizationLevel::DEVELOPMENT->value] = [
            'cache_enabled' => false,
            'query_logging' => true,
            'metadata_cache' => false,
            'batch_size' => 100,
            'lazy_loading' => true
        ];

        $this->configs[OptimizationLevel::TESTING->value] = [
            'cache_enabled' => false,
            'query_logging' => true,
            'metadata_cache' => true,
            'batch_size' => 500,
            'lazy_loading' => true
        ];

        $this->configs[OptimizationLevel::PRODUCTION->value] = [
            'cache_enabled' => true,
            'query_logging' => false,
            'metadata_cache' => true,
            'batch_size' => 1000,
            'lazy_loading' => true
        ];

        $this->configs[OptimizationLevel::HIGH_PERFORMANCE->value] = [
            'cache_enabled' => true,
            'query_logging' => false,
            'metadata_cache' => true,
            'batch_size' => 5000,
            'lazy_loading' => false, // Eager loading pour réduire les requêtes
            'connection_pooling' => true,
            'prepared_statements' => true
        ];
    }

    public function getConfig(OptimizationLevel $level): array
    {
        return $this->configs[$level->value];
    }
}
```

## Optimisation des Requêtes

### Query Optimizer

```php
<?php
class QueryOptimizer
{
    private array $optimizationRules = [];

    public function __construct()
    {
        $this->setupOptimizationRules();
    }

    public function optimizeQuery(string $sql, array $parameters = []): string
    {
        foreach ($this->optimizationRules as $rule) {
            $sql = call_user_func($rule, $sql, $parameters);
        }

        return $sql;
    }

    private function setupOptimizationRules(): void
    {
        // Éliminer les sous-requêtes inutiles
        $this->optimizationRules[] = function(string $sql, array $params): string {
            // SELECT * FROM table WHERE id IN (SELECT id FROM same_table)
            // -> SELECT * FROM table
            return preg_replace(
                '/WHERE\s+(\w+\.)?id\s+IN\s*\(\s*SELECT\s+(\w+\.)?id\s+FROM\s+(\w+)\s*\)/i',
                '',
                $sql
            );
        };

        // Optimiser les jointures
        $this->optimizationRules[] = function(string $sql, array $params): string {
            // Convertir LEFT JOIN en INNER JOIN quand possible
            if (strpos($sql, 'WHERE') !== false && strpos($sql, 'LEFT JOIN') !== false) {
                // Analyser les conditions WHERE pour voir si on peut optimiser
                return $this->optimizeJoins($sql);
            }
            return $sql;
        };

        // Optimiser LIMIT avec ORDER BY
        $this->optimizationRules[] = function(string $sql, array $params): string {
            // Ajouter des indices suggérés dans les commentaires
            if (preg_match('/ORDER BY\s+(\w+)/i', $sql, $matches)) {
                $field = $matches[1];
                return "/* USE INDEX FOR ORDER BY {$field} */ " . $sql;
            }
            return $sql;
        };
    }

    private function optimizeJoins(string $sql): string
    {
        // Logique complexe d'optimisation des jointures
        return $sql;
    }
}

// Intégration avec EmEngine
class OptimizedEmEngine extends EmEngine
{
    private QueryOptimizer $queryOptimizer;

    public function __construct(DatabaseDriverInterface $driver, QueryOptimizer $optimizer = null)
    {
        parent::__construct($driver);
        $this->queryOptimizer = $optimizer ?? new QueryOptimizer();
    }

    protected function executeQuery(string $sql, array $parameters = []): array
    {
        $optimizedSql = $this->queryOptimizer->optimizeQuery($sql, $parameters);
        return parent::executeQuery($optimizedSql, $parameters);
    }
}
```

### Index Suggestions

```php
<?php
class IndexAnalyzer
{
    private array $queryLog = [];
    private array $suggestions = [];

    public function analyzeQuery(string $sql, array $parameters = [], float $executionTime = 0): void
    {
        $this->queryLog[] = [
            'sql' => $sql,
            'parameters' => $parameters,
            'execution_time' => $executionTime,
            'timestamp' => microtime(true)
        ];

        $this->generateIndexSuggestions($sql, $executionTime);
    }

    private function generateIndexSuggestions(string $sql, float $executionTime): void
    {
        // Analyser les requêtes lentes
        if ($executionTime > 1.0) { // Plus de 1 seconde
            $this->analyzeSlow Query($sql);
        }

        // Analyser les patterns de WHERE
        if (preg_match_all('/WHERE\s+(\w+\.\w+|\w+)\s*=\s*[:\?]/i', $sql, $matches)) {
            foreach ($matches[1] as $field) {
                $this->suggestIndex($field, 'equality');
            }
        }

        // Analyser les ORDER BY
        if (preg_match_all('/ORDER BY\s+(\w+\.\w+|\w+)/i', $sql, $matches)) {
            foreach ($matches[1] as $field) {
                $this->suggestIndex($field, 'sorting');
            }
        }

        // Analyser les JOIN
        if (preg_match_all('/JOIN\s+\w+\s+\w+\s+ON\s+(\w+\.\w+|\w+)\s*=\s*(\w+\.\w+|\w+)/i', $sql, $matches)) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $this->suggestIndex($matches[1][$i], 'join');
                $this->suggestIndex($matches[2][$i], 'join');
            }
        }
    }

    private function suggestIndex(string $field, string $type): void
    {
        $key = $field . '_' . $type;
        
        if (!isset($this->suggestions[$key])) {
            $this->suggestions[$key] = [
                'field' => $field,
                'type' => $type,
                'count' => 0,
                'estimated_benefit' => 0
            ];
        }

        $this->suggestions[$key]['count']++;
        $this->suggestions[$key]['estimated_benefit'] += $this->calculateBenefit($type);
    }

    private function calculateBenefit(string $type): float
    {
        return match($type) {
            'equality' => 0.8,
            'sorting' => 0.6,
            'join' => 0.9,
            'range' => 0.7,
            default => 0.5
        };
    }

    public function getIndexSuggestions(): array
    {
        // Trier par bénéfice estimé
        uasort($this->suggestions, function($a, $b) {
            return $b['estimated_benefit'] <=> $a['estimated_benefit'];
        });

        return $this->suggestions;
    }

    public function generateIndexSQL(): array
    {
        $sqlStatements = [];
        
        foreach ($this->getIndexSuggestions() as $suggestion) {
            if ($suggestion['estimated_benefit'] > 2.0) { // Seuil de recommandation
                $field = $suggestion['field'];
                $indexName = 'idx_' . str_replace('.', '_', $field) . '_' . $suggestion['type'];
                
                $sqlStatements[] = "CREATE INDEX {$indexName} ON table_name ({$field});";
            }
        }

        return $sqlStatements;
    }
}
```

## Lazy Loading vs Eager Loading

### Contrôle du Chargement

```php
<?php
trait LoadingStrategyTrait
{
    private string $loadingStrategy = 'lazy';
    private array $eagerLoadPaths = [];

    public function setLoadingStrategy(string $strategy): void
    {
        $this->loadingStrategy = $strategy;
    }

    public function addEagerLoadPath(string $path): void
    {
        $this->eagerLoadPaths[] = $path;
    }

    public function with(array $relations): self
    {
        $this->eagerLoadPaths = array_merge($this->eagerLoadPaths, $relations);
        return $this;
    }
}

class SmartRepository extends AbstractRepository
{
    use LoadingStrategyTrait;

    public function findWithOptimalLoading(mixed $id): ?object
    {
        $entity = $this->find($id);
        
        if ($entity === null) {
            return null;
        }

        // Décider de la stratégie de chargement basée sur l'usage
        if ($this->shouldUseEagerLoading($entity)) {
            return $this->findWithEagerLoading($id, $this->predictNeededRelations($entity));
        }

        return $entity;
    }

    private function shouldUseEagerLoading(object $entity): bool
    {
        // Heuristiques pour décider du chargement eager
        $entityClass = get_class($entity);
        
        // Si on a déjà chargé cette entité plusieurs fois récemment
        if ($this->getEntityAccessCount($entityClass) > 5) {
            return true;
        }

        // Si on est dans un contexte de liste (multiple entités)
        if ($this->isListContext()) {
            return true;
        }

        return false;
    }

    private function predictNeededRelations(object $entity): array
    {
        // Prédire les relations qui seront probablement utilisées
        $entityClass = get_class($entity);
        
        $predictions = [
            User::class => ['profile', 'roles'],
            Order::class => ['customer', 'items.product'],
            Product::class => ['category', 'tags'],
            Article::class => ['author', 'category', 'tags']
        ];

        return $predictions[$entityClass] ?? [];
    }

    private function findWithEagerLoading(mixed $id, array $relations): ?object
    {
        $qb = $this->createQueryBuilder()->select('e');
        $qb->where('e.id = :id')->setParameter('id', $id);

        foreach ($relations as $relation) {
            $alias = 'rel_' . str_replace('.', '_', $relation);
            $qb->leftJoin('e.' . $relation, $alias);
        }

        return $qb->getSingleResult();
    }
}
```

### Chargement Adaptatif

```php
<?php
class AdaptiveLoadingManager
{
    private array $accessPatterns = [];
    private array $loadingDecisions = [];

    public function recordAccess(string $entityClass, string $property): void
    {
        $key = $entityClass . '::' . $property;
        
        if (!isset($this->accessPatterns[$key])) {
            $this->accessPatterns[$key] = [
                'count' => 0,
                'last_access' => null,
                'frequency' => 0
            ];
        }

        $this->accessPatterns[$key]['count']++;
        $this->accessPatterns[$key]['last_access'] = time();
        $this->updateFrequency($key);
    }

    private function updateFrequency(string $key): void
    {
        $pattern = &$this->accessPatterns[$key];
        $timeSinceFirst = time() - ($pattern['first_access'] ?? time());
        
        if ($timeSinceFirst > 0) {
            $pattern['frequency'] = $pattern['count'] / ($timeSinceFirst / 3600); // Accès par heure
        }
    }

    public function shouldEagerLoad(string $entityClass, string $property): bool
    {
        $key = $entityClass . '::' . $property;
        $pattern = $this->accessPatterns[$key] ?? null;

        if ($pattern === null) {
            return false;
        }

        // Eager load si fréquence élevée ou accès récent fréquent
        return $pattern['frequency'] > 5 || // Plus de 5 accès par heure
               ($pattern['count'] > 3 && (time() - $pattern['last_access']) < 300); // 3+ accès dans les 5 dernières minutes
    }

    public function getOptimalLoadingStrategy(string $entityClass): array
    {
        $eagerProperties = [];
        
        foreach ($this->accessPatterns as $key => $pattern) {
            if (str_starts_with($key, $entityClass . '::')) {
                $property = substr($key, strlen($entityClass . '::'));
                
                if ($this->shouldEagerLoad($entityClass, $property)) {
                    $eagerProperties[] = $property;
                }
            }
        }

        return $eagerProperties;
    }
}
```

## Batch Operations

### Batch Processor

```php
<?php
class BatchProcessor
{
    private EmEngine $emEngine;
    private int $batchSize;
    private array $pendingOperations = [];

    public function __construct(EmEngine $emEngine, int $batchSize = 1000)
    {
        $this->emEngine = $emEngine;
        $this->batchSize = $batchSize;
    }

    public function addInsert(object $entity): void
    {
        $this->pendingOperations['insert'][] = $entity;
        $this->checkBatchSize();
    }

    public function addUpdate(object $entity): void
    {
        $this->pendingOperations['update'][] = $entity;
        $this->checkBatchSize();
    }

    public function addDelete(object $entity): void
    {
        $this->pendingOperations['delete'][] = $entity;
        $this->checkBatchSize();
    }

    private function checkBatchSize(): void
    {
        $totalOperations = array_sum(array_map('count', $this->pendingOperations));
        
        if ($totalOperations >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->pendingOperations)) {
            return;
        }

        $this->emEngine->getDriver()->beginTransaction();

        try {
            // Traiter les insertions en batch
            if (!empty($this->pendingOperations['insert'])) {
                $this->processBatchInserts($this->pendingOperations['insert']);
            }

            // Traiter les mises à jour en batch
            if (!empty($this->pendingOperations['update'])) {
                $this->processBatchUpdates($this->pendingOperations['update']);
            }

            // Traiter les suppressions en batch
            if (!empty($this->pendingOperations['delete'])) {
                $this->processBatchDeletes($this->pendingOperations['delete']);
            }

            $this->emEngine->getDriver()->commit();
            $this->pendingOperations = [];

        } catch (\Exception $e) {
            $this->emEngine->getDriver()->rollback();
            throw $e;
        }
    }

    private function processBatchInserts(array $entities): void
    {
        $groupedByClass = $this->groupEntitiesByClass($entities);

        foreach ($groupedByClass as $entityClass => $classEntities) {
            $this->executeBatchInsert($entityClass, $classEntities);
        }
    }

    private function executeBatchInsert(string $entityClass, array $entities): void
    {
        $metadata = $this->emEngine->getMetadata($entityClass);
        $tableName = $metadata->getTableName();
        
        $columns = [];
        $values = [];
        $placeholders = [];

        foreach ($entities as $entity) {
            $entityData = $metadata->extractEntityData($entity);
            
            if (empty($columns)) {
                $columns = array_keys($entityData);
                $placeholders = array_fill(0, count($columns), '?');
            }
            
            $values = array_merge($values, array_values($entityData));
        }

        $columnStr = implode(', ', $columns);
        $placeholderStr = '(' . implode(', ', $placeholders) . ')';
        $valuesStr = str_repeat($placeholderStr . ', ', count($entities));
        $valuesStr = rtrim($valuesStr, ', ');

        $sql = "INSERT INTO {$tableName} ({$columnStr}) VALUES {$valuesStr}";
        
        $this->emEngine->getDriver()->executeQuery($sql, $values);
    }

    private function processBatchUpdates(array $entities): void
    {
        $groupedByClass = $this->groupEntitiesByClass($entities);

        foreach ($groupedByClass as $entityClass => $classEntities) {
            $this->executeBatchUpdate($entityClass, $classEntities);
        }
    }

    private function executeBatchUpdate(string $entityClass, array $entities): void
    {
        // Pour les updates, on peut utiliser une approche CASE WHEN
        $metadata = $this->emEngine->getMetadata($entityClass);
        $tableName = $metadata->getTableName();
        $primaryKey = $metadata->getPrimaryKeyField();
        
        $setClauses = [];
        $ids = [];
        $allValues = [];

        foreach ($entities as $entity) {
            $entityData = $metadata->extractEntityData($entity);
            $id = $entityData[$primaryKey];
            $ids[] = $id;
            
            foreach ($entityData as $field => $value) {
                if ($field !== $primaryKey) {
                    if (!isset($setClauses[$field])) {
                        $setClauses[$field] = [];
                    }
                    $setClauses[$field][] = "WHEN {$id} THEN ?";
                    $allValues[] = $value;
                }
            }
        }

        $setClausesSql = [];
        foreach ($setClauses as $field => $cases) {
            $caseSql = $field . ' = CASE ' . $primaryKey . ' ' . implode(' ', $cases) . ' END';
            $setClausesSql[] = $caseSql;
        }

        $idsPlaceholder = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE {$tableName} SET " . implode(', ', $setClausesSql) . 
               " WHERE {$primaryKey} IN ({$idsPlaceholder})";

        $this->emEngine->getDriver()->executeQuery($sql, array_merge($allValues, $ids));
    }

    private function groupEntitiesByClass(array $entities): array
    {
        $grouped = [];
        
        foreach ($entities as $entity) {
            $class = get_class($entity);
            $grouped[$class][] = $entity;
        }

        return $grouped;
    }
}
```

### Utilisation du Batch Processor

```php
<?php
class OptimizedUserService
{
    private BatchProcessor $batchProcessor;

    public function __construct(EmEngine $emEngine)
    {
        $this->batchProcessor = new BatchProcessor($emEngine, 500);
    }

    public function importUsers(array $userData): void
    {
        foreach ($userData as $data) {
            $user = new User();
            $user->setName($data['name']);
            $user->setEmail($data['email']);
            
            $this->batchProcessor->addInsert($user);
        }

        // Forcer le traitement des derniers éléments
        $this->batchProcessor->flush();
    }

    public function updateUserStatuses(array $userIds, string $status): void
    {
        // Charger par batch pour éviter la surcharge mémoire
        $chunks = array_chunk($userIds, 100);
        
        foreach ($chunks as $chunk) {
            $users = $this->emEngine->createQueryBuilder(User::class)
                ->select('u')
                ->where('u.id IN (:ids)')
                ->setParameter('ids', $chunk)
                ->getResult();

            foreach ($users as $user) {
                $user->setStatus($status);
                $this->batchProcessor->addUpdate($user);
            }
        }

        $this->batchProcessor->flush();
    }
}
```

## Profiling et Monitoring

### Performance Monitor

```php
<?php
class PerformanceMonitor
{
    private array $metrics = [];
    private array $queryLog = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function startQuery(string $sql, array $parameters = []): string
    {
        $queryId = uniqid();
        
        $this->queryLog[$queryId] = [
            'sql' => $sql,
            'parameters' => $parameters,
            'start_time' => microtime(true),
            'memory_before' => memory_get_usage(true)
        ];

        return $queryId;
    }

    public function endQuery(string $queryId, int $rowCount = 0): void
    {
        if (!isset($this->queryLog[$queryId])) {
            return;
        }

        $query = &$this->queryLog[$queryId];
        $query['end_time'] = microtime(true);
        $query['execution_time'] = $query['end_time'] - $query['start_time'];
        $query['memory_after'] = memory_get_usage(true);
        $query['memory_delta'] = $query['memory_after'] - $query['memory_before'];
        $query['row_count'] = $rowCount;

        $this->updateMetrics($query);
    }

    private function updateMetrics(array $query): void
    {
        $this->metrics['total_queries'] = ($this->metrics['total_queries'] ?? 0) + 1;
        $this->metrics['total_time'] = ($this->metrics['total_time'] ?? 0) + $query['execution_time'];
        $this->metrics['total_memory'] = ($this->metrics['total_memory'] ?? 0) + $query['memory_delta'];

        // Identifier les requêtes lentes
        if ($query['execution_time'] > 1.0) {
            $this->metrics['slow_queries'][] = $query;
        }

        // Identifier les requêtes gourmandes en mémoire
        if ($query['memory_delta'] > 1024 * 1024) { // Plus de 1MB
            $this->metrics['memory_heavy_queries'][] = $query;
        }
    }

    public function getMetrics(): array
    {
        $totalTime = microtime(true) - $this->startTime;
        
        return array_merge($this->metrics, [
            'script_execution_time' => $totalTime,
            'average_query_time' => $this->metrics['total_queries'] > 0 
                ? $this->metrics['total_time'] / $this->metrics['total_queries'] 
                : 0,
            'queries_per_second' => $totalTime > 0 
                ? $this->metrics['total_queries'] / $totalTime 
                : 0,
            'peak_memory' => memory_get_peak_usage(true),
            'current_memory' => memory_get_usage(true)
        ]);
    }

    public function getSlowQueries(float $threshold = 1.0): array
    {
        return array_filter($this->queryLog, function($query) use ($threshold) {
            return isset($query['execution_time']) && $query['execution_time'] > $threshold;
        });
    }

    public function getDuplicateQueries(): array
    {
        $querySignatures = [];
        
        foreach ($this->queryLog as $query) {
            $signature = $this->getQuerySignature($query['sql']);
            $querySignatures[$signature][] = $query;
        }

        return array_filter($querySignatures, function($queries) {
            return count($queries) > 1;
        });
    }

    private function getQuerySignature(string $sql): string
    {
        // Normaliser la requête pour identifier les doublons
        $normalized = preg_replace('/\s+/', ' ', trim($sql));
        $normalized = preg_replace('/\b\d+\b/', '?', $normalized); // Remplacer les nombres
        $normalized = preg_replace("/'[^']*'/", '?', $normalized); // Remplacer les chaînes
        
        return md5($normalized);
    }

    public function generateReport(): string
    {
        $metrics = $this->getMetrics();
        $slowQueries = $this->getSlowQueries();
        $duplicates = $this->getDuplicateQueries();

        $report = "Performance Report\n";
        $report .= "==================\n\n";

        $report .= "General Metrics:\n";
        $report .= "- Total Queries: {$metrics['total_queries']}\n";
        $report .= "- Total Time: " . number_format($metrics['total_time'], 4) . "s\n";
        $report .= "- Average Query Time: " . number_format($metrics['average_query_time'], 4) . "s\n";
        $report .= "- Queries per Second: " . number_format($metrics['queries_per_second'], 2) . "\n";
        $report .= "- Peak Memory: " . number_format($metrics['peak_memory'] / 1024 / 1024, 2) . " MB\n\n";

        if (!empty($slowQueries)) {
            $report .= "Slow Queries (" . count($slowQueries) . "):\n";
            foreach ($slowQueries as $query) {
                $report .= "- " . number_format($query['execution_time'], 4) . "s: " . 
                          substr($query['sql'], 0, 100) . "...\n";
            }
            $report .= "\n";
        }

        if (!empty($duplicates)) {
            $report .= "Duplicate Queries (" . count($duplicates) . " patterns):\n";
            foreach ($duplicates as $signature => $queries) {
                $report .= "- " . count($queries) . " occurrences: " . 
                          substr($queries[0]['sql'], 0, 100) . "...\n";
            }
        }

        return $report;
    }
}
```

### Intégration avec EmEngine

```php
<?php
class MonitoredEmEngine extends EmEngine
{
    private PerformanceMonitor $monitor;

    public function setPerformanceMonitor(PerformanceMonitor $monitor): void
    {
        $this->monitor = $monitor;
    }

    protected function executeQuery(string $sql, array $parameters = []): array
    {
        if (isset($this->monitor)) {
            $queryId = $this->monitor->startQuery($sql, $parameters);
            $result = parent::executeQuery($sql, $parameters);
            $this->monitor->endQuery($queryId, count($result));
            return $result;
        }

        return parent::executeQuery($sql, $parameters);
    }

    public function getPerformanceReport(): string
    {
        return $this->monitor?->generateReport() ?? 'No monitor configured';
    }
}
```

## Optimisation Mémoire

### Memory Manager

```php
<?php
class MemoryManager
{
    private int $maxMemoryUsage;
    private int $currentEntities = 0;
    private int $maxEntities;

    public function __construct(int $maxMemoryMB = 512, int $maxEntities = 10000)
    {
        $this->maxMemoryUsage = $maxMemoryMB * 1024 * 1024;
        $this->maxEntities = $maxEntities;
    }

    public function checkMemoryUsage(): void
    {
        $currentUsage = memory_get_usage(true);
        
        if ($currentUsage > $this->maxMemoryUsage) {
            $this->freeMemory();
        }
    }

    public function trackEntity(): void
    {
        $this->currentEntities++;
        
        if ($this->currentEntities > $this->maxEntities) {
            $this->freeMemory();
        }
    }

    private function freeMemory(): void
    {
        // Forcer le garbage collection
        gc_collect_cycles();
        
        // Réinitialiser le compteur
        $this->currentEntities = 0;
        
        // Log l'événement
        error_log("Memory cleanup triggered. Current usage: " . 
                 number_format(memory_get_usage(true) / 1024 / 1024, 2) . " MB");
    }

    public function getMemoryStats(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => $this->maxMemoryUsage,
            'entities_tracked' => $this->currentEntities,
            'entities_limit' => $this->maxEntities
        ];
    }
}

trait MemoryOptimizedTrait
{
    private ?MemoryManager $memoryManager = null;

    public function setMemoryManager(MemoryManager $manager): void
    {
        $this->memoryManager = $manager;
    }

    protected function afterEntityLoad(object $entity): void
    {
        $this->memoryManager?->trackEntity();
        $this->memoryManager?->checkMemoryUsage();
    }
}
```

### Iterator pour Grandes Collections

```php
<?php
class BatchIterator implements \Iterator
{
    private EmEngine $emEngine;
    private string $entityClass;
    private array $criteria;
    private int $batchSize;
    private int $currentOffset = 0;
    private array $currentBatch = [];
    private int $currentIndex = 0;

    public function __construct(
        EmEngine $emEngine,
        string $entityClass,
        array $criteria = [],
        int $batchSize = 1000
    ) {
        $this->emEngine = $emEngine;
        $this->entityClass = $entityClass;
        $this->criteria = $criteria;
        $this->batchSize = $batchSize;
    }

    public function rewind(): void
    {
        $this->currentOffset = 0;
        $this->currentIndex = 0;
        $this->loadBatch();
    }

    public function current(): mixed
    {
        return $this->currentBatch[$this->currentIndex] ?? null;
    }

    public function key(): mixed
    {
        return $this->currentOffset + $this->currentIndex;
    }

    public function next(): void
    {
        $this->currentIndex++;
        
        if ($this->currentIndex >= count($this->currentBatch)) {
            $this->currentOffset += $this->batchSize;
            $this->currentIndex = 0;
            $this->loadBatch();
        }
    }

    public function valid(): bool
    {
        return !empty($this->currentBatch) && $this->currentIndex < count($this->currentBatch);
    }

    private function loadBatch(): void
    {
        $qb = $this->emEngine->createQueryBuilder($this->entityClass);
        $qb->select('e');

        foreach ($this->criteria as $field => $value) {
            $qb->andWhere("e.{$field} = :{$field}")
               ->setParameter($field, $value);
        }

        $this->currentBatch = $qb->setFirstResult($this->currentOffset)
                                ->setMaxResults($this->batchSize)
                                ->getResult();

        // Libérer la mémoire des entités précédentes
        if ($this->currentOffset > 0) {
            gc_collect_cycles();
        }
    }
}

// Usage
$iterator = new BatchIterator($emEngine, User::class, ['isActive' => true], 500);

foreach ($iterator as $user) {
    // Traiter chaque utilisateur sans charger tous en mémoire
    $this->processUser($user);
}
```

## Configuration Performance

### Performance Config

```php
<?php
class PerformanceConfig
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    private function getDefaultConfig(): array
    {
        return [
            // Cache
            'cache_enabled' => true,
            'cache_driver' => 'array',
            'cache_ttl' => 3600,
            'query_cache_enabled' => true,
            'metadata_cache_enabled' => true,

            // Loading
            'default_loading_strategy' => 'lazy',
            'auto_eager_loading' => false,
            'eager_loading_threshold' => 5,

            // Batch
            'batch_size' => 1000,
            'batch_auto_flush' => true,

            // Memory
            'memory_limit_mb' => 512,
            'max_entities_in_memory' => 10000,
            'auto_gc' => true,

            // Query optimization
            'query_optimization' => true,
            'index_suggestions' => true,
            'slow_query_threshold' => 1.0,

            // Monitoring
            'performance_monitoring' => false,
            'query_logging' => false,
            'memory_tracking' => false
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function isEnabled(string $feature): bool
    {
        return (bool) $this->get($feature . '_enabled', false);
    }

    public function applyToEmEngine(EmEngine $emEngine): void
    {
        if ($this->isEnabled('performance_monitoring')) {
            $monitor = new PerformanceMonitor();
            $emEngine->setPerformanceMonitor($monitor);
        }

        if ($this->isEnabled('memory_tracking')) {
            $memoryManager = new MemoryManager(
                $this->get('memory_limit_mb'),
                $this->get('max_entities_in_memory')
            );
            $emEngine->setMemoryManager($memoryManager);
        }

        if ($this->isEnabled('cache')) {
            $cacheConfig = new EntityCacheConfig();
            $cache = $this->createCacheInstance();
            $cacheManager = new EntityCacheManager($cache, $cacheConfig);
            $emEngine->setCacheManager($cacheManager);
        }
    }

    private function createCacheInstance(): CacheInterface
    {
        $driver = $this->get('cache_driver');
        
        return match($driver) {
            'redis' => new RedisCache($this->createRedisConnection()),
            'array' => new ArrayCache(),
            default => new ArrayCache()
        };
    }
}
```

## Bonnes Pratiques

### Checklist Performance

```php
<?php
class PerformanceChecker
{
    private array $checks = [];

    public function __construct()
    {
        $this->setupChecks();
    }

    private function setupChecks(): void
    {
        $this->checks[] = [
            'name' => 'Cache Configuration',
            'check' => function(EmEngine $emEngine): array {
                $issues = [];
                
                if (!$emEngine->isCacheEnabled()) {
                    $issues[] = 'Cache is disabled - consider enabling for production';
                }
                
                return $issues;
            }
        ];

        $this->checks[] = [
            'name' => 'Query Patterns',
            'check' => function(EmEngine $emEngine): array {
                $issues = [];
                $monitor = $emEngine->getPerformanceMonitor();
                
                if ($monitor) {
                    $duplicates = $monitor->getDuplicateQueries();
                    if (count($duplicates) > 0) {
                        $issues[] = 'Found ' . count($duplicates) . ' duplicate query patterns';
                    }
                    
                    $slowQueries = $monitor->getSlowQueries(0.5);
                    if (count($slowQueries) > 0) {
                        $issues[] = 'Found ' . count($slowQueries) . ' slow queries (>0.5s)';
                    }
                }
                
                return $issues;
            }
        ];

        $this->checks[] = [
            'name' => 'Memory Usage',
            'check' => function(EmEngine $emEngine): array {
                $issues = [];
                $currentMB = memory_get_usage(true) / 1024 / 1024;
                
                if ($currentMB > 256) {
                    $issues[] = "High memory usage: {$currentMB}MB";
                }
                
                return $issues;
            }
        ];
    }

    public function runChecks(EmEngine $emEngine): array
    {
        $allIssues = [];
        
        foreach ($this->checks as $check) {
            $issues = call_user_func($check['check'], $emEngine);
            
            if (!empty($issues)) {
                $allIssues[$check['name']] = $issues;
            }
        }
        
        return $allIssues;
    }

    public function generateReport(EmEngine $emEngine): string
    {
        $issues = $this->runChecks($emEngine);
        
        if (empty($issues)) {
            return "✅ No performance issues detected\n";
        }
        
        $report = "Performance Issues Detected:\n";
        $report .= "===========================\n\n";
        
        foreach ($issues as $category => $categoryIssues) {
            $report .= "{$category}:\n";
            foreach ($categoryIssues as $issue) {
                $report .= "  ❌ {$issue}\n";
            }
            $report .= "\n";
        }
        
        return $report;
    }
}
```

### Guidelines de Performance

```php
<?php
class PerformanceGuidelines
{
    public static function getRecommendations(): array
    {
        return [
            'queries' => [
                'Always use indexes on WHERE, ORDER BY, and JOIN columns',
                'Avoid SELECT * - specify only needed columns',
                'Use LIMIT for pagination instead of loading all results',
                'Consider using raw SQL for complex reporting queries',
                'Batch multiple operations when possible'
            ],
            
            'entities' => [
                'Use lazy loading for large collections',
                'Implement eager loading for frequently accessed relations',
                'Clear entity manager periodically in long-running processes',
                'Use value objects for complex data types',
                'Avoid circular references in entity relationships'
            ],
            
            'caching' => [
                'Enable query result caching for read-heavy operations',
                'Use entity caching for frequently accessed entities',
                'Implement cache warming for critical data',
                'Set appropriate TTL values based on data volatility',
                'Monitor cache hit rates and adjust strategies'
            ],
            
            'memory' => [
                'Process large datasets in batches',
                'Use iterators for large collections',
                'Clear unused entities from memory',
                'Monitor memory usage in production',
                'Set memory limits and implement cleanup'
            ],
            
            'monitoring' => [
                'Enable performance monitoring in development',
                'Log slow queries for analysis',
                'Track memory usage patterns',
                'Monitor cache performance',
                'Set up alerts for performance degradation'
            ]
        ];
    }

    public static function printGuidelines(): void
    {
        $recommendations = self::getRecommendations();
        
        echo "Performance Guidelines\n";
        echo "======================\n\n";
        
        foreach ($recommendations as $category => $items) {
            echo strtoupper($category) . ":\n";
            foreach ($items as $item) {
                echo "  • {$item}\n";
            }
            echo "\n";
        }
    }
}
```

---

**Navigation :**
- [← Système de Cache](caching.md)
- [→ Query Builder - Requêtes Avancées](../query-builder/advanced-queries.md)
- [↑ ORM](../README.md)
