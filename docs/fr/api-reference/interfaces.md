# Interfaces Publiques - API Reference

Cette section documente toutes les interfaces publiques de MulerTech Database ORM, définissant les contrats pour l'extensibilité et l'implémentation personnalisée.

## 📋 Table des matières

- [DriverInterface](#driverinterface)
- [EntityRepositoryInterface](#entityrepositoryinterface)
- [CacheInterface](#cacheinterface)
- [EventDispatcherInterface](#eventdispatcherinterface)
- [QueryInterface](#queryinterface)
- [ConnectionInterface](#connectioninterface)
- [MetadataInterface](#metadatainterface)
- [TypeInterface](#typeinterface)

## DriverInterface

Interface définissant le contrat pour les drivers de base de données.

### Namespace
```php
MulerTech\Database\Database\Interface\DriverInterface
```

### Méthodes

#### connect()
```php
/**
 * Établit la connexion à la base de données
 *
 * @throws \RuntimeException En cas d'échec de connexion
 */
public function connect(): void;
```

#### disconnect()
```php
/**
 * Ferme la connexion à la base de données
 */
public function disconnect(): void;
```

#### isConnected()
```php
/**
 * Vérifie si la connexion est active
 *
 * @return bool
 */
public function isConnected(): bool;
```

#### execute()
```php
/**
 * Exécute une requête SQL et retourne les résultats
 *
 * @param string $sql
 * @param array<mixed> $parameters
 * @return array<array<string, mixed>>
 * @throws \RuntimeException
 */
public function execute(string $sql, array $parameters = []): array;
```

#### executeUpdate()
```php
/**
 * Exécute une requête de modification (INSERT, UPDATE, DELETE)
 *
 * @param string $sql
 * @param array<mixed> $parameters
 * @return int Nombre de lignes affectées
 * @throws \RuntimeException
 */
public function executeUpdate(string $sql, array $parameters = []): int;
```

#### getLastInsertId()
```php
/**
 * Obtient le dernier ID généré par auto-increment
 *
 * @return string|null
 */
public function getLastInsertId(): ?string;
```

#### beginTransaction()
```php
/**
 * Démarre une transaction
 *
 * @throws \RuntimeException
 */
public function beginTransaction(): void;
```

#### commit()
```php
/**
 * Valide la transaction courante
 *
 * @throws \RuntimeException
 */
public function commit(): void;
```

#### rollback()
```php
/**
 * Annule la transaction courante
 *
 * @throws \RuntimeException
 */
public function rollback(): void;
```

#### getDatabaseName()
```php
/**
 * Obtient le nom de la base de données
 *
 * @return string
 */
public function getDatabaseName(): string;
```

#### getServerVersion()
```php
/**
 * Obtient la version du serveur de base de données
 *
 * @return string
 */
public function getServerVersion(): string;
```

### Exemple d'implémentation

```php
use MulerTech\Database\Database\Interface\DriverInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CustomDriver implements DriverInterface
{
    private bool $connected = false;
    private \PDO $connection;

    public function connect(): void
    {
        try {
            $this->connection = new \PDO($this->getDsn(), $this->username, $this->password);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->connected = true;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Connection failed: ' . $e->getMessage());
        }
    }

    public function execute(string $sql, array $parameters = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ... autres méthodes
}
```

## EntityRepositoryInterface

Interface pour les repositories d'entités.

### Namespace
```php
MulerTech\Database\ORM\Repository\Interface\EntityRepositoryInterface
```

### Méthodes

#### find()
```php
/**
 * Trouve une entité par son identifiant
 *
 * @param mixed $id
 * @return object|null
 */
public function find(mixed $id): ?object;
```

#### findAll()
```php
/**
 * Trouve toutes les entités
 *
 * @return array<object>
 */
public function findAll(): array;
```

#### findBy()
```php
/**
 * Trouve des entités selon des critères
 *
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
): array;
```

#### findOneBy()
```php
/**
 * Trouve une entité selon des critères
 *
 * @param array<string, mixed> $criteria
 * @return object|null
 */
public function findOneBy(array $criteria): ?object;
```

#### count()
```php
/**
 * Compte les entités selon des critères
 *
 * @param array<string, mixed> $criteria
 * @return int
 */
public function count(array $criteria = []): int;
```

#### save()
```php
/**
 * Sauvegarde une entité
 *
 * @param object $entity
 */
public function save(object $entity): void;
```

#### delete()
```php
/**
 * Supprime une entité
 *
 * @param object $entity
 */
public function delete(object $entity): void;
```

### Exemple d'usage

```php
/**
 * @template T of object
 * @implements EntityRepositoryInterface<T>
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CustomUserRepository implements EntityRepositoryInterface
{
    /**
     * @return array<User>
     */
    public function findActiveUsers(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
}
```

## CacheInterface

Interface pour les systèmes de cache.

### Namespace
```php
MulerTech\Database\Core\Cache\CacheInterface
```

### Méthodes

#### get()
```php
/**
 * Récupère une valeur du cache
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
public function get(string $key, mixed $default = null): mixed;
```

#### set()
```php
/**
 * Stocke une valeur dans le cache
 *
 * @param string $key
 * @param mixed $value
 * @param int|null $ttl Durée de vie en secondes
 * @return bool
 */
public function set(string $key, mixed $value, ?int $ttl = null): bool;
```

#### delete()
```php
/**
 * Supprime une entrée du cache
 *
 * @param string $key
 * @return bool
 */
public function delete(string $key): bool;
```

#### clear()
```php
/**
 * Vide entièrement le cache
 *
 * @return bool
 */
public function clear(): bool;
```

#### has()
```php
/**
 * Vérifie si une clé existe dans le cache
 *
 * @param string $key
 * @return bool
 */
public function has(string $key): bool;
```

#### remember()
```php
/**
 * Récupère ou calcule et stocke une valeur
 *
 * @param string $key
 * @param callable(): mixed $callback
 * @param int|null $ttl
 * @return mixed
 */
public function remember(string $key, callable $callback, ?int $ttl = null): mixed;
```

#### deleteByPattern()
```php
/**
 * Supprime les entrées correspondant à un pattern
 *
 * @param string $pattern
 * @return int Nombre d'entrées supprimées
 */
public function deleteByPattern(string $pattern): int;
```

### Exemple d'implémentation

```php
/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class RedisCache implements CacheInterface
{
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $serialized = serialize($value);
        return $ttl 
            ? $this->redis->setex($key, $ttl, $serialized)
            : $this->redis->set($key, $serialized);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
```

## EventDispatcherInterface

Interface pour le système d'événements.

### Namespace
```php
MulerTech\Database\Event\Interface\EventDispatcherInterface
```

### Méthodes

#### dispatch()
```php
/**
 * Dispatche un événement
 *
 * @param object $event
 * @param string|null $eventName
 * @return object L'événement après dispatch
 */
public function dispatch(object $event, ?string $eventName = null): object;
```

#### addListener()
```php
/**
 * Ajoute un listener pour un événement
 *
 * @param string $eventName
 * @param callable $listener
 * @param int $priority
 */
public function addListener(string $eventName, callable $listener, int $priority = 0): void;
```

#### removeListener()
```php
/**
 * Supprime un listener
 *
 * @param string $eventName
 * @param callable $listener
 */
public function removeListener(string $eventName, callable $listener): void;
```

#### getListeners()
```php
/**
 * Obtient les listeners pour un événement
 *
 * @param string|null $eventName
 * @return array<callable>
 */
public function getListeners(?string $eventName = null): array;
```

#### hasListeners()
```php
/**
 * Vérifie si des listeners existent pour un événement
 *
 * @param string $eventName
 * @return bool
 */
public function hasListeners(string $eventName): bool;
```

## QueryInterface

Interface pour les objets de requête.

### Namespace
```php
MulerTech\Database\Query\Interface\QueryInterface
```

### Méthodes

#### getSql()
```php
/**
 * Obtient la requête SQL générée
 *
 * @return string
 */
public function getSql(): string;
```

#### getParameters()
```php
/**
 * Obtient les paramètres de la requête
 *
 * @return array<int|string, mixed>
 */
public function getParameters(): array;
```

#### execute()
```php
/**
 * Exécute la requête
 *
 * @return array<array<string, mixed>>
 */
public function execute(): array;
```

#### getResult()
```php
/**
 * Obtient le résultat sous forme d'objets
 *
 * @return array<object>
 */
public function getResult(): array;
```

#### getArrayResult()
```php
/**
 * Obtient le résultat sous forme de tableaux
 *
 * @return array<array<string, mixed>>
 */
public function getArrayResult(): array;
```

#### getSingleResult()
```php
/**
 * Obtient un seul résultat sous forme d'objet
 *
 * @return object|null
 */
public function getSingleResult(): ?object;
```

#### getSingleScalarResult()
```php
/**
 * Obtient un seul résultat scalaire
 *
 * @return mixed
 */
public function getSingleScalarResult(): mixed;
```

## ConnectionInterface

Interface pour les connexions de base de données.

### Namespace
```php
MulerTech\Database\Connection\Interface\ConnectionInterface
```

### Méthodes

#### prepare()
```php
/**
 * Prépare une requête SQL
 *
 * @param string $sql
 * @return \PDOStatement
 */
public function prepare(string $sql): \PDOStatement;
```

#### exec()
```php
/**
 * Exécute une requête SQL directement
 *
 * @param string $sql
 * @return int|false
 */
public function exec(string $sql): int|false;
```

#### query()
```php
/**
 * Exécute une requête et retourne un statement
 *
 * @param string $sql
 * @return \PDOStatement|false
 */
public function query(string $sql): \PDOStatement|false;
```

#### lastInsertId()
```php
/**
 * @param string|null $name
 * @return string|false
 */
public function lastInsertId(?string $name = null): string|false;
```

#### getAttribute()
```php
/**
 * @param int $attribute
 * @return mixed
 */
public function getAttribute(int $attribute): mixed;
```

#### setAttribute()
```php
/**
 * @param int $attribute
 * @param mixed $value
 * @return bool
 */
public function setAttribute(int $attribute, mixed $value): bool;
```

## MetadataInterface

Interface pour les métadonnées d'entité.

### Namespace
```php
MulerTech\Database\Mapping\Interface\MetadataInterface
```

### Méthodes

#### getTableName()
```php
/**
 * @return string
 */
public function getTableName(): string;
```

#### getColumnMappings()
```php
/**
 * @return array<string, ColumnMapping>
 */
public function getColumnMappings(): array;
```

#### getRelationMappings()
```php
/**
 * @return array<string, RelationMapping>
 */
public function getRelationMappings(): array;
```

#### getPrimaryKeyFields()
```php
/**
 * @return array<string>
 */
public function getPrimaryKeyFields(): array;
```

#### getFieldValue()
```php
/**
 * @param object $entity
 * @param string $field
 * @return mixed
 */
public function getFieldValue(object $entity, string $field): mixed;
```

#### setFieldValue()
```php
/**
 * @param object $entity
 * @param string $field
 * @param mixed $value
 */
public function setFieldValue(object $entity, string $field, mixed $value): void;
```

## TypeInterface

Interface pour les types de colonnes personnalisés.

### Namespace
```php
MulerTech\Database\Mapping\Types\Interface\TypeInterface
```

### Méthodes

#### getName()
```php
/**
 * @return string
 */
public function getName(): string;
```

#### getSQLDeclaration()
```php
/**
 * @param array<string, mixed> $fieldDeclaration
 * @return string
 */
public function getSQLDeclaration(array $fieldDeclaration): string;
```

#### convertToPHPValue()
```php
/**
 * @param mixed $value
 * @return mixed
 */
public function convertToPHPValue(mixed $value): mixed;
```

#### convertToDatabaseValue()
```php
/**
 * @param mixed $value
 * @return mixed
 */
public function convertToDatabaseValue(mixed $value): mixed;
```

### Exemple d'implémentation

```php
/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class UuidType implements TypeInterface
{
    public function getName(): string
    {
        return 'uuid';
    }

    public function getSQLDeclaration(array $fieldDeclaration): string
    {
        return 'CHAR(36)';
    }

    public function convertToPHPValue(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function convertToDatabaseValue(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
```

---

Ces interfaces définissent les contrats publics de MulerTech Database ORM, permettant l'extensibilité et l'implémentation de composants personnalisés tout en maintenant la compatibilité avec le système.
