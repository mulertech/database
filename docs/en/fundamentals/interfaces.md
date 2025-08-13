# Interfaces

Contracts and abstractions available in MulerTech Database for extension and testing.

## Core Interfaces

### EntityManagerInterface

The main contract for entity management operations.

```php
namespace MulerTech\Database\ORM;

interface EntityManagerInterface
{
    /**
     * @param object $entity
     */
    public function persist(object $entity): void;

    /**
     * @param object $entity
     */
    public function remove(object $entity): void;

    /**
     * Execute all pending changes
     */
    public function flush(): void;

    /**
     * Clear the identity map
     * 
     * @param string|null $className Optional entity class to clear
     */
    public function clear(?string $className = null): void;

    /**
     * @param string $className
     * @param mixed $id
     * @return object|null
     */
    public function find(string $className, mixed $id): ?object;

    /**
     * @param string $className
     * @return EntityRepository
     */
    public function getRepository(string $className): EntityRepository;

    /**
     * Start a database transaction
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     */
    public function rollback(): void;
}
```

#### Implementation Example

```php
class CustomEntityManager implements EntityManagerInterface
{
    public function __construct(
        private readonly PhpDatabaseManager $databaseManager,
        private readonly MetadataRegistry $metadataRegistry,
        private readonly CacheInterface $cache
    ) {}

    public function persist(object $entity): void
    {
        // Custom persistence logic with caching
        $this->cache->invalidate($this->getCacheKey($entity));
        // ... implementation
    }

    // ... other methods
}
```

### DatabaseManagerInterface

Contract for database connection and query execution.

```php
namespace MulerTech\Database\Database\Interface;

interface DatabaseManagerInterface
{
    /**
     * Get the database connection
     * 
     * @return PDO
     */
    public function getConnection(): PDO;

    /**
     * Execute a SELECT query
     * 
     * @param string $sql
     * @param array<mixed> $params
     * @return PDOStatement
     */
    public function executeQuery(string $sql, array $params = []): PDOStatement;

    /**
     * Execute an INSERT, UPDATE, or DELETE query
     * 
     * @param string $sql
     * @param array<mixed> $params
     * @return int Number of affected rows
     */
    public function executeUpdate(string $sql, array $params = []): int;

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     */
    public function rollback(): void;

    /**
     * Get the last inserted ID
     * 
     * @return string
     */
    public function lastInsertId(): string;
}
```

### RepositoryInterface

Base contract for repository implementations.

```php
namespace MulerTech\Database\ORM;

interface RepositoryInterface
{
    /**
     * Find entity by primary key
     * 
     * @param mixed $id
     * @return object|null
     */
    public function find(mixed $id): ?object;

    /**
     * Find all entities
     * 
     * @return array<object>
     */
    public function findAll(): array;

    /**
     * Find entities by criteria
     * 
     * @param array<string, mixed> $criteria
     * @return array<object>
     */
    public function findBy(array $criteria): array;

    /**
     * Find one entity by criteria
     * 
     * @param array<string, mixed> $criteria
     * @return object|null
     */
    public function findOneBy(array $criteria): ?object;

    /**
     * Count entities
     * 
     * @param array<string, mixed> $criteria
     * @return int
     */
    public function count(array $criteria = []): int;
}
```

### Custom Repository Interfaces

```php
// Domain-specific repository interface
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * @return array<User>
     */
    public function findActiveUsers(): array;

    /**
     * @param string $domain
     * @return array<User>
     */
    public function findByEmailDomain(string $domain): array;

    /**
     * @param DateTime $since
     * @return array<User>
     */
    public function findRecentUsers(DateTime $since): array;
}

// Implementation
class UserRepository extends EntityRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findActiveUsers(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function findByEmailDomain(string $domain): array
    {
        return $this->createQueryBuilder()
            ->where('email', 'LIKE', "%@{$domain}")
            ->getResult();
    }

    public function findRecentUsers(DateTime $since): array
    {
        return $this->createQueryBuilder()
            ->where('createdAt', '>=', $since)
            ->orderBy('createdAt', 'DESC')
            ->getResult();
    }
}
```

## Event Interfaces

### EventInterface

Base contract for all database events.

```php
namespace MulerTech\Database\Event;

interface EventInterface
{
    /**
     * Get the entity associated with this event
     * 
     * @return object
     */
    public function getEntity(): object;

    /**
     * Get the entity manager
     * 
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface;

    /**
     * Check if the event is for a specific entity type
     * 
     * @param string $className
     * @return bool
     */
    public function isEntityType(string $className): bool;
}
```

### Lifecycle Event Interfaces

```php
// Pre-persist event
interface PrePersistEventInterface extends EventInterface
{
    public function getEntity(): object;
}

// Post-persist event
interface PostPersistEventInterface extends EventInterface
{
    public function getEntity(): object;
    public function getInsertedId(): mixed;
}

// Pre-update event
interface PreUpdateEventInterface extends EventInterface
{
    public function getEntity(): object;
    public function getChangeSet(): array;
    public function hasChangedField(string $field): bool;
    public function getOldValue(string $field): mixed;
    public function getNewValue(string $field): mixed;
}

// Post-update event
interface PostUpdateEventInterface extends EventInterface
{
    public function getEntity(): object;
    public function getChangeSet(): array;
}
```

### Event Listener Interface

```php
namespace MulerTech\Database\Event;

interface EventListenerInterface
{
    /**
     * Handle pre-persist events
     * 
     * @param PrePersistEventInterface $event
     */
    public function prePersist(PrePersistEventInterface $event): void;

    /**
     * Handle post-persist events
     * 
     * @param PostPersistEventInterface $event
     */
    public function postPersist(PostPersistEventInterface $event): void;

    /**
     * Handle pre-update events
     * 
     * @param PreUpdateEventInterface $event
     */
    public function preUpdate(PreUpdateEventInterface $event): void;

    /**
     * Handle post-update events
     * 
     * @param PostUpdateEventInterface $event
     */
    public function postUpdate(PostUpdateEventInterface $event): void;

    /**
     * Handle pre-remove events
     * 
     * @param PreRemoveEventInterface $event
     */
    public function preRemove(PreRemoveEventInterface $event): void;

    /**
     * Handle post-remove events
     * 
     * @param PostRemoveEventInterface $event
     */
    public function postRemove(PostRemoveEventInterface $event): void;
}
```

## Type System Interfaces

### ColumnTypeInterface

Contract for custom column types.

```php
namespace MulerTech\Database\Mapping\Types;

interface ColumnTypeInterface
{
    /**
     * Convert a PHP value to database representation
     * 
     * @param mixed $value
     * @return mixed
     */
    public function convertToDatabaseValue(mixed $value): mixed;

    /**
     * Convert a database value to PHP representation
     * 
     * @param mixed $value
     * @return mixed
     */
    public function convertToPHPValue(mixed $value): mixed;

    /**
     * Get the SQL declaration for this type
     * 
     * @param array<string, mixed> $options
     * @return string
     */
    public function getSQLDeclaration(array $options = []): string;

    /**
     * Get the name of this type
     * 
     * @return string
     */
    public function getName(): string;
}
```

#### Custom Type Example

```php
class UuidType implements ColumnTypeInterface
{
    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        return (string) $value;
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null || $value instanceof UuidInterface) {
            return $value;
        }

        return Uuid::fromString($value);
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'CHAR(36)';
    }

    public function getName(): string
    {
        return 'uuid';
    }
}
```

## Cache Interfaces

### CacheInterface

Contract for caching implementations.

```php
namespace MulerTech\Database\Core\Cache;

interface CacheInterface
{
    /**
     * Get an item from cache
     * 
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed;

    /**
     * Store an item in cache
     * 
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl Time to live in seconds
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Check if an item exists in cache
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove an item from cache
     * 
     * @param string $key
     */
    public function delete(string $key): void;

    /**
     * Clear all cache
     */
    public function clear(): void;

    /**
     * Clear cache by pattern
     * 
     * @param string $pattern
     */
    public function clearByPattern(string $pattern): void;
}
```

## Migration Interfaces

### MigrationInterface

Contract for database migrations.

```php
namespace MulerTech\Database\Schema\Migration;

interface MigrationInterface
{
    /**
     * Apply the migration
     */
    public function up(): void;

    /**
     * Rollback the migration
     */
    public function down(): void;

    /**
     * Get the migration description
     * 
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the migration version
     * 
     * @return string
     */
    public function getVersion(): string;
}
```

### Schema Builder Interface

```php
namespace MulerTech\Database\Schema;

interface SchemaBuilderInterface
{
    /**
     * Create a table
     * 
     * @param string $tableName
     * @param callable $callback
     */
    public function createTable(string $tableName, callable $callback): void;

    /**
     * Alter a table
     * 
     * @param string $tableName
     * @param callable $callback
     */
    public function alterTable(string $tableName, callable $callback): void;

    /**
     * Drop a table
     * 
     * @param string $tableName
     */
    public function dropTable(string $tableName): void;

    /**
     * Check if table exists
     * 
     * @param string $tableName
     * @return bool
     */
    public function hasTable(string $tableName): bool;
}
```

## Usage Examples

### Dependency Injection with Interfaces

```php
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache
    ) {}

    public function createUser(string $name, string $email): User
    {
        // Check cache first
        $cacheKey = "user_email_{$email}";
        if ($this->cache->has($cacheKey)) {
            throw new UserAlreadyExistsException();
        }

        // Check database
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser) {
            $this->cache->set($cacheKey, true, 3600);
            throw new UserAlreadyExistsException();
        }

        // Create user
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
```

### Testing with Interfaces

```php
class UserServiceTest extends PHPUnit\Framework\TestCase
{
    private UserService $userService;
    private UserRepositoryInterface $userRepository;
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->userService = new UserService(
            $this->userRepository,
            $this->entityManager,
            $this->cache
        );
    }

    public function testCreateUser(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('has')
            ->with('user_email_test@example.com')
            ->willReturn(false);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $user = $this->userService->createUser('Test User', 'test@example.com');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->getName());
    }
}
```

## Best Practices

### 1. Program to Interfaces

Always depend on interfaces rather than concrete implementations:

```php
// Good
public function __construct(EntityManagerInterface $entityManager) {}

// Avoid
public function __construct(EntityManager $entityManager) {}
```

### 2. Segregated Interfaces

Keep interfaces focused and cohesive:

```php
// Good - focused interface
interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findActiveUsers(): array;
}

// Avoid - mixed responsibilities
interface UserAndPostRepositoryInterface
{
    public function findUserByEmail(string $email): ?User;
    public function findPostsByUser(User $user): array;
}
```

### 3. Interface Documentation

Use proper PHPDoc with generic types:

```php
interface RepositoryInterface
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T|null
     */
    public function find(string $className, mixed $id): ?object;
}
```

## Next Steps

- [Entity Mapping](../entity-mapping/attributes.md) - Learn about entity configuration
- [Data Access](../data-access/entity-manager.md) - Understand data operations
- [Event System](../data-access/events.md) - Work with database events
