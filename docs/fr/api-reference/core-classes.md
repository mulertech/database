# Classes Principales - API Reference

Cette section documente les classes principales de MulerTech Database ORM avec leurs méthodes, paramètres et types de retour.

## 📋 Table des matières

- [EmEngine](#emengine)
- [QueryBuilder](#querybuilder)
- [EntityRepository](#entityrepository)
- [ChangeSet](#changeset)
- [MetadataRegistry](#metadataregistry)
- [MySQLDriver](#mysqldriver)

## EmEngine

Le cœur de l'ORM, responsable de la gestion des entités et des opérations de base de données.

### Namespace
```php
MulerTech\Database\ORM\EmEngine
```

### Constructor

```php
/**
 * @param DriverInterface $driver
 * @param CacheInterface|null $cache
 * @param EventDispatcherInterface|null $eventDispatcher
 */
public function __construct(
    DriverInterface $driver,
    ?CacheInterface $cache = null,
    ?EventDispatcherInterface $eventDispatcher = null
): void
```

### Méthodes principales

#### persist()
```php
/**
 * Marque une entité pour la persistence
 *
 * @param object $entity
 * @throws \InvalidArgumentException
 */
public function persist(object $entity): void
```

#### remove()
```php
/**
 * Marque une entité pour la suppression
 *
 * @param object $entity
 * @throws \InvalidArgumentException
 */
public function remove(object $entity): void
```

#### flush()
```php
/**
 * Synchronise les changements avec la base de données
 *
 * @throws \RuntimeException
 */
public function flush(): void
```

#### find()
```php
/**
 * Trouve une entité par son ID
 *
 * @template T of object
 * @param class-string<T> $entityClass
 * @param mixed $id
 * @return T|null
 */
public function find(string $entityClass, mixed $id): ?object
```

#### getRepository()
```php
/**
 * Obtient le repository pour une classe d'entité
 *
 * @template T of object
 * @param class-string<T> $entityClass
 * @return EntityRepositoryInterface<T>
 */
public function getRepository(string $entityClass): EntityRepositoryInterface
```

#### createQueryBuilder()
```php
/**
 * Crée un nouveau QueryBuilder
 *
 * @return QueryBuilder
 */
public function createQueryBuilder(): QueryBuilder
```

#### beginTransaction()
```php
/**
 * Démarre une transaction
 *
 * @throws \RuntimeException
 */
public function beginTransaction(): void
```

#### commit()
```php
/**
 * Valide la transaction courante
 *
 * @throws \RuntimeException
 */
public function commit(): void
```

#### rollback()
```php
/**
 * Annule la transaction courante
 *
 * @throws \RuntimeException
 */
public function rollback(): void
```

#### getChangeSetManager()
```php
/**
 * Obtient le gestionnaire de changements
 *
 * @return ChangeSetManager
 */
public function getChangeSetManager(): ChangeSetManager
```

#### getMetadataRegistry()
```php
/**
 * Obtient le registre des métadonnées
 *
 * @return MetadataRegistry
 */
public function getMetadataRegistry(): MetadataRegistry
```

#### clear()
```php
/**
 * Vide le contexte de persistence
 */
public function clear(): void
```

#### detach()
```php
/**
 * Détache une entité du contexte de persistence
 *
 * @param object $entity
 */
public function detach(object $entity): void
```

#### refresh()
```php
/**
 * Recharge une entité depuis la base de données
 *
 * @param object $entity
 * @throws \InvalidArgumentException
 */
public function refresh(object $entity): void
```

### Exemples d'usage

```php
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Database\MySQLDriver;

// Initialisation
$driver = new MySQLDriver('localhost', 'database', 'user', 'password');
$em = new EmEngine($driver);

// Persistence
$user = new User('John Doe', 'john@example.com');
$em->persist($user);
$em->flush();

// Récupération
$user = $em->find(User::class, 1);

// Suppression
$em->remove($user);
$em->flush();

// Transaction
$em->beginTransaction();
try {
    $em->persist($entity1);
    $em->persist($entity2);
    $em->flush();
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}
```

## QueryBuilder

Constructeur de requêtes fluide pour créer des requêtes SQL complexes.

### Namespace
```php
MulerTech\Database\Query\Builder\QueryBuilder
```

### Méthodes de construction

#### select()
```php
/**
 * @param string|array<string> $columns
 * @return static
 */
public function select(string|array $columns): static
```

#### from()
```php
/**
 * @param string $table
 * @param string|null $alias
 * @return static
 */
public function from(string $table, ?string $alias = null): static
```

#### where()
```php
/**
 * @param string $condition
 * @return static
 */
public function where(string $condition): static
```

#### andWhere()
```php
/**
 * @param string $condition
 * @return static
 */
public function andWhere(string $condition): static
```

#### orWhere()
```php
/**
 * @param string $condition
 * @return static
 */
public function orWhere(string $condition): static
```

#### join()
```php
/**
 * @param string $table
 * @param string $condition
 * @param string|null $alias
 * @return static
 */
public function join(string $table, string $condition, ?string $alias = null): static
```

#### leftJoin()
```php
/**
 * @param string $table
 * @param string $condition
 * @param string|null $alias
 * @return static
 */
public function leftJoin(string $table, string $condition, ?string $alias = null): static
```

#### rightJoin()
```php
/**
 * @param string $table
 * @param string $condition
 * @param string|null $alias
 * @return static
 */
public function rightJoin(string $table, string $condition, ?string $alias = null): static
```

#### innerJoin()
```php
/**
 * @param string $table
 * @param string $condition
 * @param string|null $alias
 * @return static
 */
public function innerJoin(string $table, string $condition, ?string $alias = null): static
```

#### groupBy()
```php
/**
 * @param string|array<string> $columns
 * @return static
 */
public function groupBy(string|array $columns): static
```

#### having()
```php
/**
 * @param string $condition
 * @return static
 */
public function having(string $condition): static
```

#### orderBy()
```php
/**
 * @param string $column
 * @param string $direction
 * @return static
 */
public function orderBy(string $column, string $direction = 'ASC'): static
```

#### limit()
```php
/**
 * @param int $limit
 * @return static
 */
public function limit(int $limit): static
```

#### offset()
```php
/**
 * @param int $offset
 * @return static
 */
public function offset(int $offset): static
```

#### setParameter()
```php
/**
 * @param int|string $key
 * @param mixed $value
 * @return static
 */
public function setParameter(int|string $key, mixed $value): static
```

#### setParameters()
```php
/**
 * @param array<int|string, mixed> $parameters
 * @return static
 */
public function setParameters(array $parameters): static
```

### Méthodes d'exécution

#### getQuery()
```php
/**
 * @return Query
 */
public function getQuery(): Query
```

#### getSql()
```php
/**
 * @return string
 */
public function getSql(): string
```

#### getResult()
```php
/**
 * @return array<object>
 */
public function getResult(): array
```

#### getArrayResult()
```php
/**
 * @return array<array<string, mixed>>
 */
public function getArrayResult(): array
```

#### getSingleResult()
```php
/**
 * @return object|null
 */
public function getSingleResult(): ?object
```

#### getSingleScalarResult()
```php
/**
 * @return mixed
 */
public function getSingleScalarResult(): mixed
```

### Exemples d'usage

```php
// Requête simple
$users = $em->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->where('active = ?')
            ->setParameter(0, true)
            ->getQuery()
            ->getResult();

// Requête avec jointure
$posts = $em->createQueryBuilder()
            ->select('p.title, p.content, u.name as author')
            ->from('posts', 'p')
            ->innerJoin('users', 'u', 'u.id = p.user_id')
            ->where('p.published = ?')
            ->orderBy('p.created_at', 'DESC')
            ->setParameter(0, true)
            ->getQuery()
            ->getArrayResult();

// Requête d'agrégation
$count = $em->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('users')
            ->where('created_at > ?')
            ->setParameter(0, '2023-01-01')
            ->getQuery()
            ->getSingleScalarResult();
```

## EntityRepository

Classe de base pour les repositories d'entités.

### Namespace
```php
MulerTech\Database\ORM\Repository\EntityRepository
```

### Méthodes abstraites

#### getEntityClass()
```php
/**
 * @return class-string
 */
abstract protected function getEntityClass(): string;
```

### Méthodes publiques

#### find()
```php
/**
 * @param mixed $id
 * @return object|null
 */
public function find(mixed $id): ?object
```

#### findAll()
```php
/**
 * @return array<object>
 */
public function findAll(): array
```

#### findBy()
```php
/**
 * @param array<string, mixed> $criteria
 * @param array<string, string>|null $orderBy
 * @param int|null $limit
 * @param int|null $offset
 * @return array<object>
 */
public function findBy(
    array $criteria,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $offset = null
): array
```

#### findOneBy()
```php
/**
 * @param array<string, mixed> $criteria
 * @return object|null
 */
public function findOneBy(array $criteria): ?object
```

#### count()
```php
/**
 * @param array<string, mixed> $criteria
 * @return int
 */
public function count(array $criteria = []): int
```

#### createQueryBuilder()
```php
/**
 * @param string|null $alias
 * @return QueryBuilder
 */
public function createQueryBuilder(?string $alias = null): QueryBuilder
```

### Exemple d'usage

```php
use MulerTech\Database\ORM\Repository\EntityRepository;

class UserRepository extends EntityRepository
{
    protected function getEntityClass(): string
    {
        return User::class;
    }

    /**
     * @return array<User>
     */
    public function findActiveUsers(): array
    {
        return $this->findBy(['active' => true], ['name' => 'ASC']);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * @return array<User>
     */
    public function findRecentUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder()
                   ->select('*')
                   ->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->getQuery()
                   ->getResult();
    }
}
```

## ChangeSet

Représente les modifications apportées à une entité.

### Namespace
```php
MulerTech\Database\ORM\ChangeSet
```

### Méthodes publiques

#### getEntityClass()
```php
/**
 * @return class-string
 */
public function getEntityClass(): string
```

#### getEntity()
```php
/**
 * @return object
 */
public function getEntity(): object
```

#### getOperation()
```php
/**
 * @return string
 */
public function getOperation(): string
```

#### getChanges()
```php
/**
 * @return array<string, array{0: mixed, 1: mixed}>
 */
public function getChanges(): array
```

#### hasChanges()
```php
/**
 * @return bool
 */
public function hasChanges(): bool
```

#### hasChangedField()
```php
/**
 * @param string $field
 * @return bool
 */
public function hasChangedField(string $field): bool
```

#### getOldValue()
```php
/**
 * @param string $field
 * @return mixed
 */
public function getOldValue(string $field): mixed
```

#### getNewValue()
```php
/**
 * @param string $field
 * @return mixed
 */
public function getNewValue(string $field): mixed
```

### Exemple d'usage

```php
use MulerTech\Database\Event\PreUpdateEvent;

class UserEventListener
{
    public function onPreUpdate(PreUpdateEvent $event): void
    {
        $changeSet = $event->getChangeSet();
        
        if ($changeSet->getEntityClass() === User::class) {
            if ($changeSet->hasChangedField('email')) {
                $oldEmail = $changeSet->getOldValue('email');
                $newEmail = $changeSet->getNewValue('email');
                
                // Logique de notification du changement d'email
                $this->notifyEmailChange($oldEmail, $newEmail);
            }
        }
    }
}
```

## MetadataRegistry

Gestionnaire des métadonnées des entités.

### Namespace
```php
MulerTech\Database\Mapping\MetadataRegistry
```

### Méthodes publiques

#### getMetadata()
```php
/**
 * @param class-string $entityClass
 * @return EntityMetadata
 * @throws \InvalidArgumentException
 */
public function getMetadata(string $entityClass): EntityMetadata
```

#### hasMetadata()
```php
/**
 * @param class-string $entityClass
 * @return bool
 */
public function hasMetadata(string $entityClass): bool
```

#### registerMetadata()
```php
/**
 * @param class-string $entityClass
 * @param EntityMetadata $metadata
 */
public function registerMetadata(string $entityClass, EntityMetadata $metadata): void
```

#### getAllMetadata()
```php
/**
 * @return array<class-string, EntityMetadata>
 */
public function getAllMetadata(): array
```

### Exemple d'usage

```php
$registry = $em->getMetadataRegistry();
$metadata = $registry->getMetadata(User::class);

$tableName = $metadata->getTableName();
$columns = $metadata->getColumnMappings();
$relations = $metadata->getRelationMappings();
```

## MySQLDriver

Driver MySQL pour la connexion à la base de données.

### Namespace
```php
MulerTech\Database\Database\MySQLDriver
```

### Constructor

```php
/**
 * @param string $host
 * @param string $database
 * @param string $username
 * @param string $password
 * @param int $port
 * @param array<string, mixed> $options
 */
public function __construct(
    string $host,
    string $database,
    string $username,
    string $password,
    int $port = 3306,
    array $options = []
): void
```

### Méthodes publiques

#### connect()
```php
/**
 * @throws \RuntimeException
 */
public function connect(): void
```

#### disconnect()
```php
/**
 */
public function disconnect(): void
```

#### isConnected()
```php
/**
 * @return bool
 */
public function isConnected(): bool
```

#### execute()
```php
/**
 * @param string $sql
 * @param array<mixed> $parameters
 * @return array<array<string, mixed>>
 * @throws \RuntimeException
 */
public function execute(string $sql, array $parameters = []): array
```

#### executeUpdate()
```php
/**
 * @param string $sql
 * @param array<mixed> $parameters
 * @return int
 * @throws \RuntimeException
 */
public function executeUpdate(string $sql, array $parameters = []): int
```

#### getLastInsertId()
```php
/**
 * @return string|null
 */
public function getLastInsertId(): ?string
```

#### beginTransaction()
```php
/**
 * @throws \RuntimeException
 */
public function beginTransaction(): void
```

#### commit()
```php
/**
 * @throws \RuntimeException
 */
public function commit(): void
```

#### rollback()
```php
/**
 * @throws \RuntimeException
 */
public function rollback(): void
```

### Exemple d'usage

```php
use MulerTech\Database\Database\MySQLDriver;

$driver = new MySQLDriver(
    'localhost',
    'my_database',
    'username',
    'password',
    3306,
    [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ]
);

$driver->connect();

$results = $driver->execute(
    'SELECT * FROM users WHERE active = ?',
    [true]
);

$affectedRows = $driver->executeUpdate(
    'UPDATE users SET last_login = NOW() WHERE id = ?',
    [123]
);
```

---

Cette documentation de référence API couvre les classes principales de MulerTech Database ORM. Pour des détails complets sur chaque méthode, consultez le code source ou utilisez votre IDE pour la documentation PHPDoc intégrée.
