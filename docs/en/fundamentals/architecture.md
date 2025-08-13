# Architecture

Understanding the architecture and core components of MulerTech Database.

## Overview

MulerTech Database follows a layered architecture that separates concerns and provides clean abstractions:

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                        │
│  (Your Entities, Repositories, Business Logic)             │
├─────────────────────────────────────────────────────────────┤
│                      ORM Layer                              │
│  (EntityManager, Repositories, Change Tracking)            │
├─────────────────────────────────────────────────────────────┤
│                   Mapping Layer                             │
│  (Metadata Registry, Attributes, Type System)              │
├─────────────────────────────────────────────────────────────┤
│                 Database Layer (DBAL)                       │
│  (Query Builder, Connection Management)                     │
├─────────────────────────────────────────────────────────────┤
│                   Driver Layer                              │
│  (PDO, MySQL/PostgreSQL/SQLite)                            │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### 1. Entity Manager

The `EntityManager` is the central component for entity lifecycle management:

```php
use MulerTech\Database\ORM\EntityManager;

// The EntityManager coordinates all entity operations
$entityManager->persist($entity);   // Track new entity
$entityManager->remove($entity);    // Mark for deletion
$entityManager->flush();            // Execute changes
$entityManager->clear();            // Clear identity map
```

**Responsibilities:**
- Entity lifecycle management
- Change tracking coordination
- Transaction management
- Identity map maintenance

### 2. Metadata Registry

The `MetadataRegistry` stores and manages entity mapping information:

```php
use MulerTech\Database\Mapping\MetadataRegistry;

$registry = new MetadataRegistry();
$metadata = $registry->getEntityMetadata(User::class);
```

**Features:**
- Attribute-based mapping parsing
- Metadata caching
- Relationship resolution
- Column type mapping

### 3. Database Manager

The `PhpDatabaseManager` handles database connections and operations:

```php
use MulerTech\Database\Database\Interface\PhpDatabaseManager;

$pdm = new PhpDatabaseManager($config);
$connection = $pdm->getConnection();
```

**Capabilities:**
- Connection pooling
- Query execution
- Transaction management
- Multi-database support

### 4. Query Builder

The `QueryBuilder` provides a fluent API for query construction:

```php
$queryBuilder = new QueryBuilder($engine);
$results = $queryBuilder
    ->select('u.name', 'u.email')
    ->from('users', 'u')
    ->where('u.active', '=', true)
    ->getResult();
```

## Data Flow

### 1. Entity Creation Flow

```
Entity Creation → Attribute Parsing → Metadata Registry → Database Schema
```

1. **Entity Definition**: Define entity with attributes
2. **Metadata Extraction**: Parse attributes into metadata
3. **Schema Generation**: Create database tables
4. **Mapping Storage**: Store mapping information

### 2. Query Execution Flow

```
Repository Method → Query Builder → SQL Generation → Database Execution → Result Hydration
```

1. **Query Construction**: Build query using fluent API
2. **SQL Generation**: Convert to native SQL
3. **Parameter Binding**: Bind parameters safely
4. **Execution**: Execute against database
5. **Hydration**: Convert results to entities

### 3. Change Tracking Flow

```
Entity Modification → Change Detection → SQL Generation → Database Update
```

1. **State Tracking**: Monitor entity property changes
2. **Change Detection**: Identify modified properties
3. **SQL Generation**: Generate UPDATE statements
4. **Batch Execution**: Execute all changes together

## Design Patterns

### 1. Unit of Work

The EntityManager implements the Unit of Work pattern:

```php
// All changes are tracked but not executed
$user->setEmail('new@example.com');
$entityManager->persist($newUser);
$entityManager->remove($oldUser);

// Single flush executes all changes
$entityManager->flush();
```

### 2. Identity Map

Ensures each entity is loaded only once per session:

```php
$user1 = $entityManager->find(User::class, 1);
$user2 = $entityManager->find(User::class, 1);

// $user1 === $user2 (same object instance)
```

### 3. Data Mapper

Separates entity logic from persistence logic:

```php
// Entity focuses on business logic
class User
{
    public function changeEmail(string $email): void
    {
        // Business validation
        $this->email = $email;
    }
}

// Repository handles persistence
class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        // Database query logic
    }
}
```

### 4. Repository Pattern

Encapsulates data access logic:

```php
interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findActiveUsers(): array;
}

class UserRepository implements UserRepositoryInterface
{
    // Implementation details
}
```

## Performance Considerations

### 1. Lazy Loading

Related entities are loaded only when accessed:

```php
// Only User is loaded initially
$user = $entityManager->find(User::class, 1);

// Posts are loaded when accessed
$posts = $user->getPosts(); // Triggers database query
```

### 2. Query Optimization

- **Batch Operations**: Group multiple operations
- **Selective Loading**: Load only needed columns
- **Eager Loading**: Load related entities in single query
- **Query Caching**: Cache frequently used queries

### 3. Memory Management

```php
// Clear identity map to free memory
$entityManager->clear();

// Detach specific entities
$entityManager->detach($entity);
```

## Configuration Architecture

### Environment-Based Configuration

```php
// config/database.php
return [
    'default' => [
        'host' => env('DB_HOST'),
        'database' => env('DB_DATABASE'),
        // ...
    ],
    'cache' => [
        'metadata' => env('CACHE_METADATA', true),
        'queries' => env('CACHE_QUERIES', true),
    ]
];
```

### Dependency Injection Integration

```php
// Service container registration
$container->bind(EntityManagerInterface::class, function() {
    return new EntityManager($pdm, $registry);
});

$container->bind(UserRepositoryInterface::class, function($container) {
    return new UserRepository($container->get(EntityManagerInterface::class));
});
```

## Extension Points

### 1. Custom Types

```php
class UuidType implements ColumnTypeInterface
{
    public function convertToDatabaseValue($value): string
    {
        return $value->toString();
    }
    
    public function convertToPHPValue($value): UuidInterface
    {
        return Uuid::fromString($value);
    }
}
```

### 2. Event Listeners

```php
class UserEventListener
{
    public function prePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        if ($entity instanceof User) {
            $entity->setCreatedAt(new DateTime());
        }
    }
}
```

### 3. Custom Repositories

```php
class UserRepository extends EntityRepository
{
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
}
```

## Security Architecture

### 1. SQL Injection Prevention

- **Prepared Statements**: All queries use parameter binding
- **Type Validation**: Input validation at type level
- **Escape Mechanisms**: Automatic value escaping

### 2. Access Control

```php
class SecureUserRepository extends UserRepository
{
    public function findByUser(User $currentUser, int $id): ?User
    {
        // Apply access control logic
        if (!$this->canAccess($currentUser, $id)) {
            throw new AccessDeniedException();
        }
        
        return parent::find($id);
    }
}
```

## Next Steps

- [Dependency Injection](dependency-injection.md) - Configure dependency injection
- [Core Classes](core-classes.md) - Detailed API reference
- [Interfaces](interfaces.md) - Available contracts and abstractions
