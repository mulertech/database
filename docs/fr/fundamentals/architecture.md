# Architecture Générale

🌍 **Languages:** [🇫🇷 Français](architecture.md) | [🇬🇧 English](../../en/core-concepts/architecture.md)

---

## 📋 Table des Matières

- [Vue d'Ensemble](#vue-densemble)
- [Composants Principaux](#composants-principaux)
- [Flux de Données](#flux-de-données)
- [Patterns Architecturaux](#patterns-architecturaux)
- [Cycle de Vie des Entités](#cycle-de-vie-des-entités)
- [Couches et Responsabilités](#couches-et-responsabilités)
- [Diagrammes d'Architecture](#diagrammes-darchitecture)

---

## Vue d'Ensemble

MulerTech Database suit une **architecture en couches** inspirée des principes **Domain-Driven Design (DDD)** et utilise plusieurs patterns éprouvés pour offrir une solution ORM robuste et performante.

### 🏗️ Philosophie Architecturale

```
┌─────────────────────────────────────────────────────────┐
│                   APPLICATION LAYER                     │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │
│  │  Services   │  │ Controllers │  │  Commands   │      │
│  └─────────────┘  └─────────────┘  └─────────────┘      │
└─────────────────────────────────────────────────────────┘
           │                    │                    │
┌─────────────────────────────────────────────────────────┐
│                     DOMAIN LAYER                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │
│  │  Entities   │  │ Repositories│  │  Events     │      │
│  └─────────────┘  └─────────────┘  └─────────────┘      │
└─────────────────────────────────────────────────────────┘
           │                    │                    │
┌─────────────────────────────────────────────────────────┐
│                   INFRASTRUCTURE LAYER                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │
│  │ EntityManager│  │Query Builder│  │   Cache     │      │
│  └─────────────┘  └─────────────┘  └─────────────┘      │
└─────────────────────────────────────────────────────────┘
           │                    │                    │
┌─────────────────────────────────────────────────────────┐
│                   DATABASE LAYER                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │
│  │    PDO      │  │   Drivers   │  │ Connections │      │
│  └─────────────┘  └─────────────┘  └─────────────┘      │
└─────────────────────────────────────────────────────────┘
```

### 🎯 Objectifs Architecturaux

1. **Séparation des responsabilités** : Chaque couche a un rôle bien défini
2. **Faible couplage** : Les composants sont indépendants
3. **Haute cohésion** : Les éléments liés sont regroupés
4. **Testabilité** : Architecture facilitant les tests unitaires
5. **Extensibilité** : Possibilité d'ajouter de nouvelles fonctionnalités
6. **Performance** : Optimisations à tous les niveaux

---

## Composants Principaux

### 🗄️ EntityManager

Le **point d'entrée principal** de l'ORM, responsable de la gestion des entités.

```php
interface EntityManagerInterface
{
    // Accès aux composants internes
    public function getEmEngine(): EmEngine;
    public function getPdm(): PhpDatabaseInterface;
    public function getMetadataRegistry(): MetadataRegistry;
    public function getEventManager(): ?EventManager;
    
    // Récupération
    public function find(string $entity, string|int $idOrWhere): ?object;
    
    // Repositories
    public function getRepository(string $entity): EntityRepository;
}
```

**Responsabilités :**
- Orchestrer les opérations CRUD via EmEngine
- Gérer l'accès aux repositories
- Interfacer avec la base de données
- Coordination des événements

### ⚙️ EmEngine (Entity Manager Engine)

Le **cœur technique** qui implémente la logique métier de l'ORM.

```php
class EmEngine
{
    private StateManagerInterface $stateManager;
    private IdentityMap $identityMap;
    private ChangeDetector $changeDetector;
    private ChangeSetManager $changeSetManager;
    private EntityHydrator $hydrator;
    private EntityRegistry $entityRegistry;
    private PersistenceManager $persistenceManager;
    private RelationManager $relationManager;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        MetadataRegistry $metadataRegistry
    ) {
        // Initialisation des composants
    }
}
```

**Responsabilités :**
- Gestion des états d'entités via StateManager
- Change Detection (détection des modifications)
- Orchestration des opérations de persistance
- Hydratation des entités
- Gestion des relations

### 📊 MetadataRegistry

Le **registre des métadonnées** qui contient les informations de mapping.

```php
class MetadataRegistry
{
    private array $metadata = [];
    private EntityProcessor $entityProcessor;
    
    public function __construct(?string $entitiesPath = null);
    public function loadEntitiesFromPath(string $entitiesPath): void;
    public function getEntityMetadata(string $class): EntityMetadata;
    public function hasEntityMetadata(string $class): bool;
}
```

**Responsabilités :**
- Analyser les attributs des entités via EntityProcessor
- Stocker les métadonnées de mapping
- Chargement automatique des entités
- Validation des entités

### 🔍 Query Builder

Le **constructeur de requêtes** avec une API fluide.

```php
class QueryBuilder
{
    public function select(string ...$columns): SelectBuilder;
    public function insert(string $table): InsertBuilder;
    public function update(string $table): UpdateBuilder;
    public function delete(string $table): DeleteBuilder;
}
```

**Responsabilités :**
- Construire des requêtes SQL dynamiquement
- Validation et sécurisation des requêtes
- Optimisation des requêtes
- Support des requêtes complexes

### 🗂️ Repository Pattern

Le **pattern Repository** pour encapsuler la logique d'accès aux données.

```php
class EntityRepository
{
    protected EntityManagerInterface $entityManager;
    private string $entityName;
    
    public function find(string|int $id): ?object;
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    protected function createQueryBuilder(): QueryBuilder;
    protected function getTableName(): string;
}
```

---

## Flux de Données

### 📥 Flux de Lecture (Read Operations)

```mermaid
graph TD
    A[Application] --> B[EntityManager]
    B --> C[Repository]
    C --> D[EmEngine]
    D --> E{Identity Map?}
    E -->|Hit| F[Return Cached Entity]
    E -->|Miss| G[Query Builder]
    G --> H[Database]
    H --> I[Raw Results]
    I --> J[Entity Hydrator]
    J --> K[Store in Identity Map]
    K --> L[Return Entity]
```

**Étapes détaillées :**

1. **Application** fait une demande via EntityManager
2. **EntityManager** délègue au Repository approprié
3. **Repository** vérifie l'Identity Map via EmEngine
4. Si **cache miss**, construction de la requête
5. **Exécution** de la requête en base
6. **Hydratation** des résultats en entités
7. **Stockage** dans l'Identity Map
8. **Retour** de l'entité à l'application

### 📤 Flux d'Écriture (Write Operations)

```mermaid
graph TD
    A[Application] --> B[EmEngine.persist]
    B --> C[StateManager]
    C --> D[Entity State Management]
    D --> E[Track Changes]
    E --> F[Application.flush]
    F --> G[Change Detection]
    G --> H[PersistenceManager]
    H --> I[SQL Generation]
    I --> J[Transaction]
    J --> K[Database]
    K --> L[Update Identity Map]
```

**Étapes détaillées :**

1. **persist()** marque l'entité pour persistance via StateManager
2. **Suivi** des modifications dans le ChangeSet
3. **flush()** déclenche la synchronisation
4. **Détection** des changements (dirty checking)
5. **Planification** des opérations via PersistenceManager
6. **Génération** du SQL optimisé
7. **Exécution** dans une transaction
8. **Mise à jour** des caches et métadonnées

---

## Patterns Architecturaux

### 🔄 Persistence Manager Pattern

Gère les modifications comme une **unité atomique** via le PersistenceManager.

```php
class PersistenceManager
{
    private InsertionProcessor $insertionProcessor;
    private UpdateProcessor $updateProcessor;
    private DeletionProcessor $deletionProcessor;
    
    public function flush(): void
    {
        $this->insertionProcessor->processInsertions();
        $this->updateProcessor->processUpdates();
        $this->deletionProcessor->processDeletions();
    }
}
```

**Avantages :**
- **Atomicité** : Tout ou rien
- **Performance** : Batch des opérations
- **Cohérence** : Ordre d'exécution optimal
- **Rollback** : Annulation en cas d'erreur

### 🗺️ Identity Map Pattern

**Cache** des entités en mémoire pour éviter les doublons.

```php
class IdentityMap
{
    private array $entities = [];
    private WeakMap $metadata;
    private WeakMap $entityIds;
    
    public function contains(string $entityClass, int|string $id): bool;
    public function get(string $entityClass, int|string $id): ?object;
    public function add(object $entity): void;
    public function remove(object $entity): void;
}
```

**Avantages :**
- **Performance** : Évite les requêtes redondantes
- **Cohérence** : Une seule instance par ID
- **Mémoire** : Gestion optimisée via WeakMap

### 📊 Data Mapper Pattern

**Séparation** entre le modèle objet et la base de données.

```php
class EntityHydrator
{
    public function __construct(private readonly MetadataRegistry $metadataRegistry);
    
    public function hydrateEntity(string $class, array $data): object;
    public function hydrateCollection(string $class, array $rows): array;
}
```

### 🎯 Repository Pattern

**Encapsulation** de la logique d'accès aux données.

```php
class EntityRepository
{
    protected EntityManagerInterface $entityManager;
    private string $entityName;
    
    public function find(string|int $id): ?object;
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    protected function createQueryBuilder(): QueryBuilder;
    protected function getTableName(): string;
}
```

---

## Cycle de Vie des Entités

### 📋 États des Entités

```php
enum EntityLifecycleState: string
{
    case NEW = 'new';        // Nouvelle entité, pas encore persistée
    case MANAGED = 'managed'; // Entité gérée par l'EntityManager
    case DETACHED = 'detached'; // Entité détachée du contexte
    case REMOVED = 'removed';  // Entité marquée pour suppression
    
    public function canTransitionTo(EntityLifecycleState $to): bool;
}
```

### 🔄 Transitions d'État

```mermaid
stateDiagram-v2
    [*] --> NEW : new Entity()
    NEW --> MANAGED : persist()
    NEW --> DETACHED : detach()
    NEW --> REMOVED : remove()
    MANAGED --> REMOVED : remove()
    MANAGED --> DETACHED : detach()
    REMOVED --> [*] : flush()
    DETACHED --> [*] : GC
```

**Gestion des transitions :**

```php
class StateManagerInterface
{
    public function getEntityState(object $entity): EntityLifecycleState;
    public function transitionTo(object $entity, EntityLifecycleState $newState): void;
    public function isManaged(object $entity): bool;
    public function isNew(object $entity): bool;
    public function isRemoved(object $entity): bool;
    public function isDetached(object $entity): bool;
}
```

---

## Couches et Responsabilités

### 🎨 Application Layer

**Responsabilités :**
- Orchestration des cas d'usage
- Coordination des services
- Gestion des transactions métier
- Interface avec l'utilisateur

**Composants :**
- Services applicatifs
- Commandes et gestionnaires
- Controllers (dans un contexte web)
- DTOs et transformateurs

### 🏢 Domain Layer

**Responsabilités :**
- Logique métier pure
- Règles de validation
- Entités du domaine
- Événements métier

**Composants :**
- Entités avec leur logique
- Value Objects
- Domain Services
- Events et Event Handlers

### 🔧 Infrastructure Layer

**Responsabilités :**
- Persistance des données
- Accès aux services externes
- Configuration technique
- Implémentation des interfaces

**Composants :**
- EntityManager et EmEngine
- Query Builder
- Cache et optimisations
- Drivers de base de données

### 💾 Database Layer

**Responsabilités :**
- Connexions à la base
- Exécution des requêtes
- Gestion des transactions
- Optimisations SQL

**Composants :**
- PDO et drivers
- Connection pooling
- Query execution
- Transaction management

---

## Diagrammes d'Architecture

### 🏗️ Architecture des Composants

```
┌─────────────────────────────────────────────────────────┐
│                    APPLICATION                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────┐    ┌─────────────────┐             │
│  │   UserService   │    │   BlogService   │             │
│  └─────────────────┘    └─────────────────┘             │
│           │                       │                     │
├───────────┼───────────────────────┼─────────────────────┤
│           │                       │                     │
│  ┌─────────────────┐    ┌─────────────────┐             │
│  │ UserRepository  │    │ PostRepository  │             │
│  └─────────────────┘    └─────────────────┘             │
│           │                       │                     │
│           └───────────┬───────────┘                     │
│                       │                                 │
│              ┌─────────────────┐                        │
│              │ EntityManager   │                        │
│              └─────────────────┘                        │
│                       │                                 │
├───────────────────────┼─────────────────────────────────┤
│                       │                                 │
│              ┌─────────────────┐                        │
│              │    EmEngine     │                        │
│              └─────────────────┘                        │
│                       │                                 │
│  ┌─────────────────┐  │  ┌─────────────────┐            │
│  │  IdentityMap    │  │  │ ChangeSetMgr    │            │
│  └─────────────────┘  │  └─────────────────┘            │
│                       │                                 │
│  ┌─────────────────┐  │  ┌─────────────────┐            │
│  │ QueryBuilder    │  │  │MetadataRegistry │            │
│  └─────────────────┘  │  └─────────────────┘            │
│                       │                                 │
├───────────────────────┼─────────────────────────────────┤
│                       │                                 │
│              ┌─────────────────┐                        │
│              │PhpDatabaseMgr   │                        │
│              └─────────────────┘                        │
│                       │                                 │
│              ┌─────────────────┐                        │
│              │      PDO        │                        │
│              └─────────────────┘                        │
└─────────────────────────────────────────────────────────┘
```

### 🔄 Flux d'Exécution Complet

```mermaid
sequenceDiagram
    participant App as Application
    participant EM as EntityManager
    participant EE as EmEngine
    participant MR as MetadataRegistry
    participant IM as IdentityMap
    participant QB as QueryBuilder
    participant DB as Database
    
    App->>EM: find(User::class, 1)
    EM->>EE: find(User::class, 1)
    EE->>IM: get(User::class, 1)
    
    alt Entity in cache
        IM-->>EE: Return cached entity
        EE-->>EM: Return entity
        EM-->>App: Return entity
    else Cache miss
        EE->>MR: getEntityMetadata(User::class)
        MR-->>EE: EntityMetadata
        EE->>QB: select().from().where()
        QB->>DB: Execute SQL
        DB-->>QB: Raw result
        QB-->>EE: Raw result
        EE->>EE: Hydrate entity
        EE->>IM: store(entity)
        EE-->>EM: Return entity
        EM-->>App: Return entity
    end
```

---

## ➡️ Étapes Suivantes

Pour approfondir votre compréhension :

1. 🔧 [Configuration](configuration.md) - Configuration avancée
2. 💉 [Injection de Dépendances](dependency-injection.md) - Intégration DI
3. 🎨 [Attributs de Mapping](../../fr/entity-mapping/attributes.md) - Mapping détaillé
4. 🗄️ [Entity Manager](../../fr/orm/entity-manager.md) - API complète

---

## 🔗 Liens Utiles

- 🏠 [Retour au README](../../fr/../README.md)
- 📖 [Documentation Complète](../../fr/README.md)
- 🚀 [Démarrage Rapide](../../fr/quick-start/installation.md)
- 🎯 [Exemples Pratiques](../../fr/quick-start/basic-examples.md)
