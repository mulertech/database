# Core Classes

Reference guide for the main classes in MulerTech Database.

## EntityManager

The central component for entity lifecycle management.

### Class: `MulerTech\Database\ORM\EntityManager`

```php
class EntityManager implements EntityManagerInterface
{
    public function persist(object $entity): void;
    public function remove(object $entity): void;
    public function flush(): void;
    public function clear(): void;
    public function find(string $className, mixed $id): ?object;
    public function getRepository(string $className): EntityRepository;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
}
```

#### Key Methods

**persist(object $entity): void**
- Marks an entity for insertion on next flush
- Entity must be a new instance (not yet persisted)

```php
$user = new User();
$user->setName('John Doe');
$entityManager->persist($user);
```

**remove(object $entity): void**
- Marks an entity for deletion on next flush
- Entity must be managed (previously persisted)

```php
$user = $entityManager->find(User::class, 1);
$entityManager->remove($user);
```

**flush(): void**
- Executes all pending changes to the database
- Automatically detects changes in managed entities

```php
$user->setEmail('new@example.com'); // Change is tracked
$entityManager->flush(); // UPDATE executed
```

**find(string $className, mixed $id): ?object**
- Finds an entity by primary key
- Returns null if not found

```php
$user = $entityManager->find(User::class, 1);
if ($user === null) {
    throw new UserNotFoundException();
}
```

## MetadataRegistry

Manages entity mapping metadata and caching.

### Class: `MulerTech\Database\Mapping\MetadataRegistry`

```php
class MetadataRegistry
{
    public function getEntityMetadata(string $className): EntityMetadata;
    public function hasEntityMetadata(string $className): bool;
    public function registerEntity(string $className): void;
    public function getAllEntityMetadata(): array;
}
```

#### Usage Examples

```php
$registry = new MetadataRegistry();

// Get metadata for an entity
$metadata = $registry->getEntityMetadata(User::class);
$tableName = $metadata->getTableName();
$columns = $metadata->getColumns();

// Register entities manually
$registry->registerEntity(User::class);
$registry->registerEntity(Post::class);
```

## EntityMetadata

Contains mapping information for a single entity.

### Class: `MulerTech\Database\Mapping\EntityMetadata`

```php
class EntityMetadata
{
    public function getClassName(): string;
    public function getTableName(): string;
    public function getColumns(): array;
    public function getPrimaryKey(): ?ColumnMapping;
    public function getColumnMapping(string $property): ?ColumnMapping;
    public function getRelations(): array;
}
```

#### Properties and Methods

```php
$metadata = $registry->getEntityMetadata(User::class);

// Basic information
$className = $metadata->getClassName(); // "App\Entity\User"
$tableName = $metadata->getTableName(); // "users"

// Column information
$columns = $metadata->getColumns(); // Array of ColumnMapping
$primaryKey = $metadata->getPrimaryKey(); // ColumnMapping for ID

// Get specific column
$emailColumn = $metadata->getColumnMapping('email');
if ($emailColumn) {
    $columnName = $emailColumn->getColumnName(); // "email"
    $columnType = $emailColumn->getColumnType(); // ColumnType::VARCHAR
}
```

## EntityRepository

Base repository class for data access operations.

### Class: `MulerTech\Database\ORM\EntityRepository`

```php
class EntityRepository
{
    public function find(mixed $id): ?object;
    public function findAll(): array;
    public function findBy(array $criteria): array;
    public function findOneBy(array $criteria): ?object;
    public function count(array $criteria = []): int;
    public function createQueryBuilder(): QueryBuilder;
}
```

#### Standard Methods

**find(mixed $id): ?object**
```php
$user = $userRepository->find(1);
```

**findAll(): array**
```php
$allUsers = $userRepository->findAll();
```

**findBy(array $criteria): array**
```php
$activeUsers = $userRepository->findBy(['active' => true]);
$recentUsers = $userRepository->findBy(['createdAt' => '> 2024-01-01']);
```

**findOneBy(array $criteria): ?object**
```php
$user = $userRepository->findOneBy(['email' => 'john@example.com']);
```

**count(array $criteria = []): int**
```php
$totalUsers = $userRepository->count();
$activeUsers = $userRepository->count(['active' => true]);
```

#### Custom Repository Example

```php
class UserRepository extends EntityRepository
{
    public function findByEmailDomain(string $domain): array
    {
        return $this->createQueryBuilder()
            ->where('email', 'LIKE', "%@{$domain}")
            ->getResult();
    }

    public function findActiveUsersByRole(string $role): array
    {
        return $this->findBy([
            'active' => true,
            'role' => $role
        ]);
    }
}
```

## QueryBuilder

Fluent API for building database queries.

### Class: `MulerTech\Database\Query\QueryBuilder`

```php
class QueryBuilder
{
    public function select(string ...$columns): self;
    public function from(string $table, ?string $alias = null): self;
    public function where(string $column, string $operator, mixed $value): self;
    public function join(string $table, string $alias, string $condition): self;
    public function leftJoin(string $table, string $alias, string $condition): self;
    public function orderBy(string $column, string $direction = 'ASC'): self;
    public function groupBy(string ...$columns): self;
    public function having(string $column, string $operator, mixed $value): self;
    public function limit(int $limit): self;
    public function offset(int $offset): self;
    public function getResult(): array;
    public function getSingleResult(): ?array;
    public function getSQL(): string;
}
```

#### Query Building Examples

**Basic Select**
```php
$users = $queryBuilder
    ->select('id', 'name', 'email')
    ->from('users')
    ->where('active', '=', true)
    ->orderBy('name')
    ->getResult();
```

**Joins**
```php
$results = $queryBuilder
    ->select('u.name', 'p.title')
    ->from('users', 'u')
    ->join('posts', 'p', 'u.id = p.user_id')
    ->where('p.published', '=', true)
    ->getResult();
```

**Aggregation**
```php
$counts = $queryBuilder
    ->select('category', 'COUNT(*) as count')
    ->from('products')
    ->groupBy('category')
    ->having('COUNT(*)', '>', 5)
    ->getResult();
```

## PhpDatabaseManager

Handles database connections and low-level operations.

### Class: `MulerTech\Database\Database\Interface\PhpDatabaseManager`

```php
class PhpDatabaseManager
{
    public function __construct(array $config);
    public function getConnection(): PDO;
    public function executeQuery(string $sql, array $params = []): PDOStatement;
    public function executeUpdate(string $sql, array $params = []): int;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
    public function lastInsertId(): string;
}
```

#### Connection Management

```php
$config = [
    'host' => 'localhost',
    'database' => 'my_app',
    'username' => 'user',
    'password' => 'pass'
];

$pdm = new PhpDatabaseManager($config);
$connection = $pdm->getConnection();
```

#### Query Execution

```php
// Select query
$stmt = $pdm->executeQuery(
    'SELECT * FROM users WHERE active = ?',
    [true]
);
$users = $stmt->fetchAll();

// Update query
$affectedRows = $pdm->executeUpdate(
    'UPDATE users SET last_login = ? WHERE id = ?',
    [new DateTime(), 1]
);
```

## ColumnMapping

Represents the mapping between entity properties and database columns.

### Class: `MulerTech\Database\Mapping\ColumnMapping`

```php
class ColumnMapping
{
    public function getPropertyName(): string;
    public function getColumnName(): string;
    public function getColumnType(): ColumnType;
    public function getLength(): ?int;
    public function getPrecision(): ?int;
    public function getScale(): ?int;
    public function isNullable(): bool;
    public function isUnique(): bool;
    public function getDefaultValue(): mixed;
}
```

#### Usage Example

```php
$metadata = $registry->getEntityMetadata(User::class);
$emailMapping = $metadata->getColumnMapping('email');

if ($emailMapping) {
    echo $emailMapping->getColumnName();    // "email"
    echo $emailMapping->getColumnType();    // ColumnType::VARCHAR
    echo $emailMapping->getLength();        // 255
    echo $emailMapping->isNullable();       // false
    echo $emailMapping->isUnique();         // true
}
```

## ChangeDetector

Tracks changes to managed entities.

### Class: `MulerTech\Database\ORM\ChangeDetector`

```php
class ChangeDetector
{
    public function detectChanges(object $entity, array $originalData): array;
    public function hasChanges(object $entity, array $originalData): bool;
    public function getPropertyChanges(object $entity, array $originalData): array;
}
```

#### Change Detection

```php
$changeDetector = new ChangeDetector();

// Entity is loaded with original data
$user = $entityManager->find(User::class, 1);
$originalData = ['name' => 'John', 'email' => 'john@old.com'];

// User makes changes
$user->setEmail('john@new.com');

// Detect what changed
$changes = $changeDetector->detectChanges($user, $originalData);
// Result: ['email' => ['john@old.com', 'john@new.com']]
```

## Performance Considerations

### Memory Usage

```php
// Clear identity map to free memory
$entityManager->clear();

// Or clear specific entity type
$entityManager->clear(User::class);
```

### Query Optimization

```php
// Use specific columns instead of SELECT *
$queryBuilder
    ->select('id', 'name') // Only what you need
    ->from('users');

// Use LIMIT for large result sets
$queryBuilder
    ->select('*')
    ->from('users')
    ->limit(100);
```

### Batch Operations

```php
// Process large datasets in batches
$batchSize = 1000;
$processed = 0;

while (true) {
    $users = $userRepository->createQueryBuilder()
        ->limit($batchSize)
        ->offset($processed)
        ->getResult();
    
    if (empty($users)) {
        break;
    }
    
    foreach ($users as $user) {
        // Process user
    }
    
    $entityManager->flush();
    $entityManager->clear(); // Free memory
    
    $processed += $batchSize;
}
```

## Next Steps

- [Interfaces](interfaces.md) - Learn about available contracts
- [Entity Manager](../data-access/entity-manager.md) - Deep dive into entity management
- [Query Builder](../data-access/query-builder.md) - Advanced query construction
