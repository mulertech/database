# Plan de Refonte MulerTech Database - Documentation Complète

## 🎯 Objectifs Généraux

### Performance
- Implémentation d'un cache intelligent multicouche
- Optimisation des requêtes avec préparation et réutilisation
- Batch processing natif pour réduire les allers-retours DB
- Identity Map pour éviter les requêtes redondantes
- Lazy loading optimisé avec stratégies configurable

### Architecture
- Séparation claire des responsabilités (SRP)
- Pattern Strategy pour les différents types de relations
- Factory patterns pour l'instanciation optimisée
- Injection de dépendances moderne
- Architecture modulaire avec interfaces bien définies

### Code Quality
- PHP 8.3+ avec types stricts partout
- Readonly properties et immutabilité maximale
- PHPDoc complet compatible PHPStan Level 8
- Couverture de tests unitaires et d'intégration
- Suppression complète de la duplication de code

---

## 📋 Architecture Cible

### Core Namespace Structure
```
MulerTech\Database\
├── Cache\                  # Système de cache unifié
├── Connection\             # Gestion des connexions
├── Query\                  # Builders modulaires
├── ORM\                    # Entity management
├── Relations\              # Gestion des relations
├── Batch\                  # Opérations en lot
├── Events\                 # Système d'événements
├── Mapping\                # Métadonnées et attributs
├── Migration\              # Système de migrations
├── Debug\                  # Outils de développement
└── Exception\              # Exceptions spécialisées
```

---

## 🏗️ Plan de Développement - 6 Phases

### **Phase 1 : Foundation Layer (Priorité Critique)**
*Refonte des classes fondamentales avec cache intégré*

#### 1.1 Connection Management
**Classes à créer :**
- `Connection\ConnectionPool` - Pool de connexions avec configuration
- `Connection\ConnectionManager` - Gestionnaire principal
- `Connection\PreparedStatementCache` - Cache des statements préparés

**Classes à modifier :**
- `PhpInterface\PhpDatabaseManager` → Intégration cache statements
- `PhpInterface\Statement` → Optimisations et métriques

#### 1.2 Query Architecture Modulaire
**Classes à créer :**
- `Query\AbstractQueryBuilder` - Base commune
- `Query\SelectBuilder` - SELECT optimisé
- `Query\InsertBuilder` - INSERT avec batch support
- `Query\UpdateBuilder` - UPDATE avec conditions complexes
- `Query\DeleteBuilder` - DELETE optimisé
- `Query\QueryCompiler` - Compilation et cache des requêtes
- `Query\QueryOptimizer` - Optimisations automatiques

**Classes à remplacer :**
- `Relational\Sql\QueryBuilder` → Architecture modulaire

#### 1.3 Cache System Unifié
**Classes à créer :**
- `Cache\CacheInterface` - Interface unifiée
- `Cache\MemoryCache` - Cache mémoire avec LRU
- `Cache\MetadataCache` - Cache spécialisé métadonnées
- `Cache\QueryCache` - Cache des requêtes compilées
- `Cache\ResultSetCache` - Cache des résultats
- `Cache\CacheInvalidator` - Invalidation intelligente

### **Phase 2 : ORM Core Refactoring (Priorité Haute)**
*Refonte complète du moteur ORM*

#### 2.1 Entity Management
**Classes à créer :**
- `ORM\IdentityMap` - Cache des entités chargées
- `ORM\EntityRegistry` - Registre global des entités
- `ORM\UnitOfWork` - Gestionnaire des modifications
- `ORM\ChangeDetector` - Détection optimisée des changements
- `ORM\EntityFactory` - Factory optimisée avec cache

**Classes à refactoriser :**
- `ORM\EntityManager` → Integration IdentityMap + UnitOfWork
- `ORM\EmEngine` → Simplification et optimisation
- `ORM\EntityHydrator` → Cache des métadonnées reflection

#### 2.2 State Management
**Classes à créer :**
- `ORM\State\EntityState` - Enum des états d'entité
- `ORM\State\StateTransition` - Gestionnaire des transitions
- `ORM\State\StateValidator` - Validation des états

**Classes à remplacer :**
- `ORM\Engine\EntityState\EntityStateManager` → Intégration enum + validation
- `ORM\Engine\EntityState\EntityChangeTracker` → Optimisation algorithmique

### **Phase 3 : Relations & Lazy Loading (Priorité Moyenne)**
*Système de relations optimisé avec stratégies*

#### 3.1 Relation Processors
**Classes à créer :**
- `Relations\RelationProcessorInterface` - Interface commune
- `Relations\OneToManyProcessor` - Traitement OneToMany optimisé
- `Relations\ManyToManyProcessor` - Traitement ManyToMany avec cache
- `Relations\OneToOneProcessor` - Traitement OneToOne
- `Relations\RelationFactory` - Factory des processors
- `Relations\LazyLoader` - Chargement différé intelligent
- `Relations\RelationCache` - Cache spécialisé relations

**Classes à remplacer :**
- `ORM\EntityRelationLoader` → Architecture Strategy
- `ORM\Engine\Relations\RelationManager` → Modularité

#### 3.2 Collections Optimisées
**Classes à créer :**
- `Collections\LazyCollection` - Collection avec lazy loading
- `Collections\BatchCollection` - Collection avec opérations batch
- `Collections\CachedCollection` - Collection avec cache intégré
- `Collections\CollectionFactory` - Factory des collections

**Classes à modifier :**
- `ORM\DatabaseCollection` → Héritage des nouvelles collections

### **Phase 4 : Batch Processing (Priorité Moyenne)**
*Opérations en lot native*

#### 4.1 Batch Operations
**Classes à créer :**
- `Batch\BatchProcessor` - Processeur principal
- `Batch\InsertBatch` - INSERT en lot optimisé
- `Batch\UpdateBatch` - UPDATE en lot avec conditions
- `Batch\DeleteBatch` - DELETE en lot
- `Batch\BatchStrategy` - Stratégies de traitement
- `Batch\ChunkProcessor` - Découpage automatique
- `Batch\TransactionManager` - Gestion transactions batch

### **Phase 5 : Performance & Monitoring (Priorité Basse)**
*Outils de développement et monitoring*

#### 5.1 Debug & Monitoring
**Classes à créer :**
- `Debug\QueryProfiler` - Profiling des requêtes
- `Debug\PerformanceMonitor` - Monitoring performances
- `Debug\SlowQueryDetector` - Détection requêtes lentes
- `Debug\NPlusOneDetector` - Détection problème N+1
- `Debug\CacheHitRateMonitor` - Statistiques cache
- `Debug\DebugBar` - Barre de debug intégrée

### **Phase 6 : Migration & Cleanup (Priorité Basse)**
*Nettoyage et optimisation finale*

#### 6.1 Code Cleanup
- Suppression des classes obsolètes
- Migration des tests
- Documentation complète
- Benchmarks de performance

---

## 📊 Matrice de Remplacement des Classes

### Classes à Remplacer Complètement
| Classe Actuelle | Nouvelle Classe | Raison | Phase |
|----------------|-----------------|---------|-------|
| `QueryBuilder` | `Query\SelectBuilder` + modules | Architecture modulaire | 1 |
| `EmEngine` | `ORM\EntityEngine` | Séparation responsabilités | 2 |
| `EntityStateManager` | `ORM\State\StateManager` | Enum + validation | 2 |
| `EntityChangeTracker` | `ORM\ChangeDetector` | Algorithme optimisé | 2 |
| `EntityRelationLoader` | `Relations\RelationProcessor` | Pattern Strategy | 3 |
| `RelationManager` | `Relations\RelationFactory` | Modularité | 3 |

### Classes à Refactoriser
| Classe Actuelle | Modifications | Phase |
|----------------|---------------|-------|
| `PhpDatabaseManager` | + Cache statements, pool connexions | 1 |
| `EntityManager` | + IdentityMap, UnitOfWork, optimisations | 2 |
| `EntityHydrator` | + Cache métadonnées reflection | 2 |
| `DatabaseCollection` | + Lazy loading, batch operations | 3 |
| `DbMapping` | + Cache métadonnées, lazy loading | 2 |

### Nouvelles Classes Principales
| Classe | Responsabilité | Phase |
|--------|---------------|-------|
| `IdentityMap` | Cache entités en mémoire | 2 |
| `QueryCache` | Cache requêtes compilées | 1 |
| `BatchProcessor` | Opérations en lot | 4 |
| `PerformanceMonitor` | Monitoring temps réel | 5 |
| `LazyLoader` | Chargement différé optimisé | 3 |

---

## 🔧 Détails Techniques par Phase

### Phase 1 - Foundation

#### Connection Pool
```php
/**
 * Pool de connexions avec gestion automatique
 * @package MulerTech\Database\Connection
 * @author Sébastien Muler
 */
class ConnectionPool
{
    /** @var array<string, \PDO> */
    private readonly array $connections;
    
    /** @var PreparedStatementCache */
    private readonly PreparedStatementCache $statementCache;
    
    public function getConnection(string $key = 'default'): \PDO;
    public function releaseConnection(string $key): void;
}
```

#### Query Builders Modulaires
```php
/**
 * Builder SELECT optimisé avec cache
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
class SelectBuilder extends AbstractQueryBuilder
{
    public function select(string ...$columns): self;
    public function from(string $table, ?string $alias = null): self;
    public function join(string $table, string $condition, string $type = 'INNER'): self;
    public function where(string|SqlOperations $condition): self;
    public function orderBy(string $column, string $direction = 'ASC'): self;
    public function limit(int $limit, int $offset = 0): self;
    public function compile(): string; // Avec cache
}
```

### Phase 2 - ORM Core

#### Identity Map
```php
/**
 * Cache intelligent des entités chargées
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
class IdentityMap
{
    /** @var array<string, array<int|string, object>> */
    private array $entities = [];
    
    public function get(string $entityClass, int|string $id): ?object;
    public function set(string $entityClass, int|string $id, object $entity): void;
    public function remove(string $entityClass, int|string $id): void;
    public function clear(?string $entityClass = null): void;
    public function contains(string $entityClass, int|string $id): bool;
}
```

#### Unit of Work
```php
/**
 * Gestionnaire des modifications d'entités
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
class UnitOfWork
{
    /** @var array<int, object> */
    private array $newEntities = [];
    
    /** @var array<int, object> */
    private array $dirtyEntities = [];
    
    /** @var array<int, object> */
    private array $removedEntities = [];
    
    public function registerNew(object $entity): void;
    public function registerDirty(object $entity): void;
    public function registerRemoved(object $entity): void;
    public function commit(): void;
}
```

### Phase 3 - Relations

#### Relation Processor Strategy
```php
/**
 * Interface pour les processeurs de relations
 * @package MulerTech\Database\Relations
 * @author Sébastien Muler
 */
interface RelationProcessorInterface
{
    public function load(object $entity, string $property, array $metadata): mixed;
    public function persist(object $entity, string $property, mixed $value): void;
    public function supports(string $relationType): bool;
}

/**
 * Processeur OneToMany optimisé
 * @package MulerTech\Database\Relations
 * @author Sébastien Muler
 */
class OneToManyProcessor implements RelationProcessorInterface
{
    public function load(object $entity, string $property, array $metadata): LazyCollection;
    public function persist(object $entity, string $property, mixed $value): void;
    public function supports(string $relationType): bool;
}
```

### Phase 4 - Batch Processing

#### Batch Processor
```php
/**
 * Processeur d'opérations en lot
 * @package MulerTech\Database\Batch
 * @author Sébastien Muler
 */
class BatchProcessor
{
    public function batchInsert(string $entityClass, array $data, int $chunkSize = 1000): int;
    public function batchUpdate(string $entityClass, array $updates, array $criteria = []): int;
    public function batchDelete(string $entityClass, array $criteria): int;
    
    /** @return array<string, mixed> */
    public function getStatistics(): array;
}
```

---

## 📝 Étapes de Migration Détaillées

### Étape 1.1 : Connection Layer
1. Créer `ConnectionPool` avec configuration
2. Créer `PreparedStatementCache` avec LRU
3. Modifier `PhpDatabaseManager` pour intégrer le cache
4. Tests unitaires complets

### Étape 1.2 : Query Builders
1. Créer `AbstractQueryBuilder` avec cache intégré
2. Implémenter `SelectBuilder` avec optimisations
3. Implémenter `InsertBuilder`, `UpdateBuilder`, `DeleteBuilder`
4. Créer `QueryCompiler` avec cache des requêtes
5. Tests de performance comparatifs

### Étape 2.1 : ORM Foundation
1. Créer `IdentityMap` avec gestion mémoire
2. Créer `UnitOfWork` avec détection changements
3. Refactoriser `EntityManager` pour intégrer les nouveaux composants
4. Migration progressive des tests existants

### Étape 2.2 : State Management
1. Créer enum `EntityState`
2. Implémenter `StateTransition` avec validation
3. Refactoriser `EntityStateManager`
4. Tests d'intégration avec les nouvelles classes

### Étape 3.1 : Relations Strategy
1. Définir `RelationProcessorInterface`
2. Implémenter chaque processeur spécialisé
3. Créer `RelationFactory` pour la sélection
4. Intégrer avec `LazyLoader`

### Étape 3.2 : Collections Optimisées
1. Créer `LazyCollection` avec chargement différé
2. Créer `BatchCollection` pour opérations lot
3. Intégrer avec les relation processors
4. Tests de performance lazy loading

### Étape 4.1 : Batch Operations
1. Créer `BatchProcessor` avec stratégies
2. Implémenter chunking automatique
3. Intégrer avec `TransactionManager`
4. Benchmarks performance vs requêtes individuelles

### Étape 5.1 : Monitoring
1. Créer `PerformanceMonitor` temps réel
2. Implémenter `SlowQueryDetector`
3. Créer `NPlusOneDetector`
4. Interface de debug intégrée

---

## 🎯 Métriques de Succès

### Performance
- Réduction 70% du nombre de requêtes (Identity Map + Lazy Loading)
- Amélioration 50% temps de réponse (Cache + Batch)
- Réduction 60% utilisation mémoire (Optimisations collection)

### Code Quality
- 0 duplication code (SonarQube)
- PHPStan Level 8 sans erreur
- Couverture tests 95%+
- Complexité cyclomatique < 10

### Maintenabilité
- Séparation claire responsabilités
- Architecture modulaire
- Documentation complète API
- Guides migration pour utilisateurs

---

## 🚀 Points d'Attention pour Continuation

### Architecture Decisions
- **Cache Strategy** : LRU avec TTL configurable
- **Lazy Loading** : Proxy pattern avec interface transparente
- **Batch Size** : Configurable par entité (défaut 1000)
- **Transaction Scope** : Auto-commit vs manual pour batch

### Compatibility Requirements
- **PHP 8.3+** : Types stricts, readonly, enums
- **Backward Compatibility** : Adapter pattern pour transition
- **Database Support** : MySQL prioritaire, PostgreSQL secondaire

### Performance Targets
- **Query Reduction** : 70% moins de requêtes DB
- **Memory Usage** : 60% réduction consommation
- **Response Time** : 50% amélioration moyenne
- **Cache Hit Rate** : 85%+ pour métadonnées

### Testing Strategy
- **Unit Tests** : Chaque classe isolément
- **Integration Tests** : Flux complets ORM
- **Performance Tests** : Benchmarks avant/après
- **Load Tests** : Simulation charge réelle

Cette documentation complète permet de reprendre le développement à n'importe quelle phase, avec une vision claire de l'architecture cible et des étapes de migration.