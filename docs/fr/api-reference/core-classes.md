# Classes Principales - API Reference

Cette section documente les classes présentes dans MulerTech Database ORM avec leur rôle et leurs méthodes publiques principales.

## 📋 Table des matières

- [EntityManager (API publique)](#entitymanager-api-publique)
- [EmEngine (interne)](#emengine-interne)
- [QueryBuilder (fabrique)](#querybuilder-fabrique)
  - [SelectBuilder](#selectbuilder)
  - [InsertBuilder](#insertbuilder)
  - [UpdateBuilder](#updatebuilder)
  - [DeleteBuilder](#deletebuilder)
  - [RawQueryBuilder](#rawquerybuilder)
- [EntityRepository](#entityrepository)
- [ChangeSet](#changeset)
- [MetadataRegistry](#metadataregistry)
- [MySQLDriver](#mysqldriver)

---
## EntityManager (API publique)

Facade principale utilisée par l'application. Il orchestre EmEngine, l'hydratation et l'accès aux métadonnées.

### Namespace
```php
MulerTech\Database\ORM\EntityManager
```

### Construction (exemple)
```php
$metadataRegistry = new MetadataRegistry(__DIR__.'/src/Entity');
$pdoLayer = new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []);
$eventManager = new EventManager();
$entityManager = new EntityManager($pdoLayer, $metadataRegistry, $eventManager);
```

### Méthodes publiques clés
| Méthode | Signature | Rôle |
|---------|-----------|------|
| getEmEngine | `getEmEngine(): EmEngine` | Accès moteur interne |
| getPdm | `getPdm(): PhpDatabaseInterface` | Accès couche DB abstraite |
| getMetadataRegistry | `getMetadataRegistry(): MetadataRegistry` | Métadonnées entités |
| getHydrator | `getHydrator(): EntityHydrator` | Hydrateur bas niveau |
| getEventManager | `getEventManager(): ?EventManager` | Dispatcher d'événements |
| getRepository | `getRepository(class-string $entity): EntityRepository` | Repository spécifique |
| find | `find(class-string $entity, string|int $idOrWhere): ?object` | Recherche simple (id ou where brut) |
| rowCount | `rowCount(class-string $entity, ?string $where=null): int` | Comptage simple |
| persist | `persist(object $entity): void` | Marquer pour insertion / update |
| remove | `remove(object $entity): void` | Marquer suppression |
| flush | `flush(): void` | Propager changements |
| merge | `merge(object $entity): object` | (Pass-through EmEngine) |
| detach | `detach(object $entity): void` | Sortir du contexte |
| refresh | `refresh(object $entity): void` | Re-synchronisation (si possible) |
| clear | `clear(): void` | Réinitialisation du contexte |

> Le support transactionnel bas-niveau (begin/commit/rollback) n'est pas exposé ici dans le code actuel examiné.

### Exemple minimal
```php
$user = new User('Alice');
$entityManager->persist($user);
$entityManager->flush();
$found = $entityManager->find(User::class, $user->getId());
```

---
## EmEngine (interne)

Moteur interne orchestrant : IdentityMap, ChangeDetector, ChangeSetManager, Persistence & State Managers.

### Namespace
```php
MulerTech\Database\ORM\EmEngine
```

### Points importants
- Méthodes publiques principales exposées : `find()`, `persist()`, `remove()`, `flush()`, `detach()`, `clear()`, `rowCount()`.
- Charge les entités via `QueryBuilder` + hydratation.
- Met à jour sélectivement les entités déjà gérées (optimisation IdentityMap).

### Exemple (usage indirect via EntityManager)
```php
$emEngine = $entityManager->getEmEngine();
$entity = $emEngine->find(User::class, 10);
```

---
## QueryBuilder (fabrique)

Classe de fabrique créant des builders spécialisés. Elle n'implémente pas directement les méthodes de filtrage (where, join, ...), celles-ci résident dans les builders retournés.

### Namespace
```php
MulerTech\Database\Query\Builder\QueryBuilder
```

### Méthodes
| Méthode | Signature | Retour |
|---------|-----------|--------|
| select | `select(string ...$columns): SelectBuilder` | Builder SELECT |
| insert | `insert(string $table): InsertBuilder` | Builder INSERT |
| update | `update(string $table, ?string $alias=null): UpdateBuilder` | Builder UPDATE |
| delete | `delete(string $table, ?string $alias=null): DeleteBuilder` | Builder DELETE |
| raw | `raw(string $sql): RawQueryBuilder` | Builder requête brute |

### Exemple
```php
$qb = new QueryBuilder($entityManager->getEmEngine());
$rows = $qb->select('u.id','u.email')
    ->from('users','u')
    ->where('u.active', 1)
    ->orderBy('u.id','DESC')
    ->limit(10)
    ->fetchAll();
```

> Les méthodes `where()`, `orderBy()`, `limit()` etc. appartiennent à `SelectBuilder` (ou autres builders), pas à `QueryBuilder` lui‑même.

### Méthodes communes (SelectBuilder)
| Méthode | Existence | Note |
|---------|-----------|------|
| select(...$cols) | oui | Ajout / fusion colonnes |
| from(table, alias?) | oui | Table ou sous-requête |
| where(col, val, op=EQUAL) | via trait | Construction paramétrée |
| orderBy(col, dir) | oui | Tri multi-colonne |
| limit(int) | oui | Limitation |
| offset(int|page) | oui | Requiert `limit` préalable |
| groupBy(...cols) | oui | Agrégation |
| having(col, val, op) | oui | Filtre aggrégé |
| distinct() / withoutDistinct() | oui | Marqueurs |
| join/leftJoin/rightJoin/innerJoin | via JoinClauseTrait | Relations multi-table |
| fetchAll() | hérité AbstractQueryBuilder | Exécution résultat (array) |
| fetchScalar() | hérité | Première valeur |
| fetchOne() | hérité | Première ligne |
| getSql() | hérité | Génération SQL |

---
## Insert / Update / Delete / Raw Builders

### Signatures de fabrique
```php
$insert = $qb->insert('users')->values(['email' => 'a@b.c']);
$update = $qb->update('users')->set('email', 'new@b.c')->where('id', 5);
$delete = $qb->delete('users')->where('id', 5);
$raw    = $qb->raw('SELECT 1');
```

> Les méthodes précises (ex: `set()`, `values()`) se trouvent dans chaque builder dédié (`InsertBuilder`, `UpdateBuilder`, `DeleteBuilder`, `RawQueryBuilder`).

---
## EntityRepository

Implémentation générique fournie par le core. Les repositories personnalisés sont instanciés via métadonnées (attribut `MtEntity(repository: ...)`).

### Namespace
```php
MulerTech\Database\ORM\EntityRepository
```

### Construction (interne)
```php
new EntityRepository(EntityManagerInterface $em, string $entityClass);
```

### Méthodes publiques
| Méthode | Signature | Description |
|---------|-----------|-------------|
| find | `find(string|int $id): ?object` | Recherche simple |
| findBy | `findBy(array $criteria, ?array $orderBy=null, ?int $limit=null, ?int $offset=null): array` | Liste filtrée |
| findOneBy | `findOneBy(array $criteria, ?array $orderBy=null): ?object` | Première occurrence |
| findAll | `findAll(): array` | Tous les enregistrements (⚠ dataset large) |
| count | `count(array $criteria = []): int` | Compte filtré |
| getEntityManager | `getEntityManager(): EntityManagerInterface` | Accès EM |
| __call | `__call(string $method, array $args): array|object|null` | `findByX`, `findOneByY` dynamiques |

> La création d'un QueryBuilder se fait en interne (`createQueryBuilder()` protégé).

---
## ChangeSet

Représentation immuable des modifications détectées pour une entité donnée.

### Namespace
```php
MulerTech\Database\ORM\ChangeSet
```

### Structure
```php
final readonly class ChangeSet {
    public function __construct(
        public string $entityClass,
        /** @var array<string, PropertyChange> */
        public array $changes
    ) {}
    public function getChanges(): array {}
    public function isEmpty(): bool {}
    public function getFieldChange(string $field): ?PropertyChange {}
    public function filter(callable $cb): self {}
}
```

### Notes
- Aucune méthode `getEntity()`, `getOperation()`, `getOldValue()` ou `getNewValue()` n'existe dans la classe actuelle.
- Les anciennes signatures documentées ont été retirées de cette référence.

### Exemple d'inspection
```php
$changeSet = $changeDetector->buildChangeSet($entity);
if (!$changeSet->isEmpty()) {
    $emailChange = $changeSet->getFieldChange('email');
}
```

---
## MetadataRegistry

Cache mémoire immuable des métadonnées d'entités, construit à la demande via `EntityProcessor`.

### Namespace
```php
MulerTech\Database\Mapping\MetadataRegistry
```

### Méthodes utiles
| Méthode | Signature | Rôle |
|---------|-----------|------|
| getEntityMetadata | `getEntityMetadata(class-string $class): EntityMetadata` | Charge ou renvoie le cache |
| hasMetadata | `hasMetadata(class-string $class): bool` | Présence cache |
| registerMetadata | `registerMetadata(class-string, EntityMetadata $m): void` | Injection manuelle (tests) |
| getRegisteredClasses | `getRegisteredClasses(): array<class-string>` | Liste classes chargées |
| getAllMetadata | `getAllMetadata(): array<class-string, EntityMetadata>` | Dump complet |
| clear | `clear(): void` | Réinitialisation |
| loadEntitiesFromPath | `loadEntitiesFromPath(string $path): void` | Scan & enregistrement |
| getTableName (legacy) | `getTableName(class-string): string` | Compat utilitaire |
| getPropertiesColumns (legacy) | `getPropertiesColumns(class-string, bool $withoutId=true): array<string,string>` | Mapping propriété→colonne |

### Exemple
```php
$registry = new MetadataRegistry(__DIR__.'/Entity');
$meta = $registry->getEntityMetadata(User::class);
$table = $meta->tableName;
$columns = $meta->getPropertiesColumns();
```

---
## MySQLDriver

Actuellement limité à la génération de DSN (la logique de connexion/ exécution repose sur d'autres abstractions).

### Namespace
```php
MulerTech\Database\Database\MySQLDriver
```

### Méthode publique
| Méthode | Signature | Rôle |
|---------|-----------|------|
| generateDsn | `generateDsn(array $options): string` | Construit un DSN PDO MySQL |

```php
$driver = new MySQLDriver();
$dsn = $driver->generateDsn([
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'app',
    'charset' => 'utf8mb4'
]);
$pdo = new PDO($dsn, 'user', 'pass');
```
