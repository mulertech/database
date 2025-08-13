# Entity Manager

Complete guide to using the Entity Manager for entity lifecycle management in MulerTech Database.

## Overview

The Entity Manager is the central component that coordinates all entity operations. It implements the Unit of Work pattern, tracking changes to entities and executing them efficiently in batches.

```php
use MulerTech\Database\ORM\EntityManagerInterface;

// Basic usage
$entityManager->persist($entity);   // Track for insertion
$entityManager->remove($entity);    // Mark for deletion  
$entityManager->flush();            // Execute all changes
```

## Core Operations

### persist()

Marks a new entity for insertion into the database.

```php
public function createUser(string $name, string $email): User
{
    $user = new User();
    $user->setName($name);
    $user->setEmail($email);
    
    // Mark entity for insertion
    $entityManager->persist($user);
    
    // Execute the INSERT
    $entityManager->flush();
    
    // Entity now has an ID
    echo "Created user with ID: " . $user->getId();
    
    return $user;
}
```

**Important notes:**
- Only call `persist()` for NEW entities
- The entity gets an ID only after `flush()`
- Entity becomes "managed" after successful insertion

### remove()

Marks a managed entity for deletion.

```php
public function deleteUser(int $userId): bool
{
    $user = $entityManager->find(User::class, $userId);
    
    if (!$user) {
        return false;
    }
    
    // Mark for deletion
    $entityManager->remove($user);
    
    // Execute the DELETE
    $entityManager->flush();
    
    return true;
}
```

**Important notes:**
- Only works with managed entities (loaded from database)
- Entity is removed from identity map after deletion
- Consider cascading deletes for related entities

### flush()

Executes all pending changes to the database.

```php
public function updateMultipleUsers(array $userData): void
{
    foreach ($userData as $id => $data) {
        $user = $entityManager->find(User::class, $id);
        if ($user) {
            $user->setName($data['name']);
            $user->setEmail($data['email']);
            // Changes are automatically tracked
        }
    }
    
    // Execute all UPDATEs in single transaction
    $entityManager->flush();
}
```

**Performance benefits:**
- Batches multiple operations
- Single database transaction
- Optimized SQL generation
- Automatic change detection

### find()

Loads a single entity by primary key.

```php
// Find by primary key
$user = $entityManager->find(User::class, 1);

if ($user === null) {
    throw new UserNotFoundException();
}

// Entity is now "managed" and tracked for changes
$user->setEmail('new@example.com');
$entityManager->flush(); // UPDATE executed automatically
```

**Identity Map:**
- Ensures one instance per entity per session
- Subsequent calls return same object
- Improves performance and consistency

```php
$user1 = $entityManager->find(User::class, 1);
$user2 = $entityManager->find(User::class, 1);

// $user1 === $user2 (same object instance)
```

### clear()

Clears the identity map and stops tracking entities.

```php
// Clear all entities
$entityManager->clear();

// Clear specific entity type
$entityManager->clear(User::class);

// Useful for long-running processes
foreach ($largeDataset as $data) {
    // Process entities...
    
    if ($processedCount % 1000 === 0) {
        $entityManager->flush();
        $entityManager->clear(); // Free memory
    }
}
```

## Change Tracking

The Entity Manager automatically tracks changes to managed entities.

### Automatic Detection

```php
// Load entity
$user = $entityManager->find(User::class, 1);
$originalEmail = $user->getEmail();

// Modify properties
$user->setEmail('updated@example.com');
$user->setName('Updated Name');

// Changes are detected automatically
$entityManager->flush(); // Generates: UPDATE users SET email = ?, name = ? WHERE id = 1
```

### Change Detection Process

1. **Snapshot Creation**: Original state stored when entity is loaded
2. **Property Monitoring**: Changes tracked when setters are called
3. **Diff Calculation**: Changes computed during `flush()`
4. **SQL Generation**: Only modified fields included in UPDATE

### Manual State Management

```php
// Detach entity from tracking
$entityManager->detach($user);
$user->setEmail('changed@example.com'); // This change won't be persisted

// Re-attach entity
$entityManager->merge($user); // Changes will be detected again
```

## Repository Access

Get repositories for entity-specific operations.

```php
// Get default repository
$userRepository = $entityManager->getRepository(User::class);

// Repository provides convenient methods
$users = $userRepository->findAll();
$user = $userRepository->find(1);
$activeUsers = $userRepository->findBy(['active' => true]);
```

### Custom Repositories

```php
// Define custom repository
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

// Register in entity mapping
#[MtEntity(
    tableName: 'users',
    repositoryClass: UserRepository::class
)]
class User
{
    // ...
}

// Access custom methods
$userRepository = $entityManager->getRepository(User::class);
$activeUsers = $userRepository->findActiveUsers();
```

## Transaction Management

### Manual Transactions

```php
try {
    $entityManager->beginTransaction();
    
    // Create user
    $user = new User();
    $user->setName('John Doe');
    $entityManager->persist($user);
    
    // Create profile
    $profile = new UserProfile();
    $profile->setUserId($user->getId());
    $entityManager->persist($profile);
    
    // Commit all changes
    $entityManager->commit();
    $entityManager->flush();
    
} catch (Exception $e) {
    $entityManager->rollback();
    throw $e;
}
```

### Automatic Transactions

```php
// flush() automatically wraps operations in transaction
$user = new User();
$user->setName('Jane Doe');
$entityManager->persist($user);

$post = new Post();
$post->setTitle('My Post');
$post->setAuthorId($user->getId());
$entityManager->persist($post);

// Both INSERT operations executed in single transaction
$entityManager->flush();
```

## Entity States

Understanding entity lifecycle states.

### New (Transient)

Entity exists in memory but not in database.

```php
$user = new User(); // New/Transient state
$user->setName('John');

// Entity is not tracked
$entityManager->flush(); // Nothing happens
```

### Managed

Entity is tracked by Entity Manager.

```php
// Becomes managed after persist() + flush()
$entityManager->persist($user);
$entityManager->flush(); // Now managed

// Or when loaded from database
$user = $entityManager->find(User::class, 1); // Managed

// Changes are automatically tracked
$user->setEmail('new@example.com');
$entityManager->flush(); // UPDATE executed
```

### Detached

Entity exists but is not tracked.

```php
$user = $entityManager->find(User::class, 1);
$entityManager->detach($user); // Now detached

$user->setEmail('changed@example.com');
$entityManager->flush(); // No UPDATE - changes ignored
```

### Removed

Entity marked for deletion.

```php
$user = $entityManager->find(User::class, 1);
$entityManager->remove($user); // Now in removed state

$entityManager->flush(); // DELETE executed
// Entity becomes detached after deletion
```

## Advanced Usage

### Batch Operations

Efficient processing of large datasets.

```php
public function importUsers(array $userData): void
{
    $batchSize = 100;
    $count = 0;
    
    foreach ($userData as $data) {
        $user = new User();
        $user->setName($data['name']);
        $user->setEmail($data['email']);
        
        $entityManager->persist($user);
        
        if (++$count % $batchSize === 0) {
            $entityManager->flush();  // Execute batch
            $entityManager->clear();  // Free memory
        }
    }
    
    // Flush remaining entities
    $entityManager->flush();
}
```

### Memory Management

```php
public function processLargeDataset(): void
{
    $offset = 0;
    $limit = 1000;
    
    while (true) {
        $users = $entityManager->getRepository(User::class)
            ->createQueryBuilder()
            ->limit($limit)
            ->offset($offset)
            ->getResult();
        
        if (empty($users)) {
            break;
        }
        
        foreach ($users as $user) {
            // Process user
            $user->setLastProcessed(new DateTime());
        }
        
        $entityManager->flush();
        $entityManager->clear(); // Important: free memory
        
        $offset += $limit;
    }
}
```

### Event Integration

```php
class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}
    
    public function createUser(string $name, string $email): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        
        // Dispatch pre-persist event
        $this->eventDispatcher->dispatch(new UserCreatingEvent($user));
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        // Dispatch post-persist event
        $this->eventDispatcher->dispatch(new UserCreatedEvent($user));
        
        return $user;
    }
}
```

## Performance Considerations

### 1. Batch Operations

```php
// Good - batch operations
foreach ($entities as $entity) {
    $entityManager->persist($entity);
}
$entityManager->flush(); // Single transaction

// Avoid - individual flushes
foreach ($entities as $entity) {
    $entityManager->persist($entity);
    $entityManager->flush(); // Multiple transactions - slower
}
```

### 2. Memory Management

```php
// Good - clear when processing large datasets
if ($processedCount % 1000 === 0) {
    $entityManager->flush();
    $entityManager->clear();
}

// Monitor memory usage
echo "Memory usage: " . memory_get_usage(true) . " bytes\n";
```

### 3. Selective Loading

```php
// Load only needed data
$queryBuilder = $entityManager->getRepository(User::class)
    ->createQueryBuilder()
    ->select('id', 'name', 'email') // Only needed columns
    ->where('active', '=', true)
    ->limit(100);
```

## Error Handling

### Database Constraints

```php
try {
    $user = new User();
    $user->setEmail('existing@example.com'); // Duplicate email
    
    $entityManager->persist($user);
    $entityManager->flush();
    
} catch (UniqueConstraintViolationException $e) {
    // Handle duplicate email
    throw new UserAlreadyExistsException('Email already exists');
    
} catch (DatabaseException $e) {
    // Handle other database errors
    $entityManager->rollback();
    throw new PersistenceException('Failed to save user', 0, $e);
}
```

### Entity Validation

```php
public function createUser(string $name, string $email): User
{
    // Validate before persisting
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email format');
    }
    
    // Check for existing user
    $existing = $entityManager->getRepository(User::class)
        ->findOneBy(['email' => $email]);
    
    if ($existing) {
        throw new UserAlreadyExistsException();
    }
    
    $user = new User();
    $user->setName($name);
    $user->setEmail($email);
    
    $entityManager->persist($user);
    $entityManager->flush();
    
    return $user;
}
```

## Best Practices

### 1. Service Layer Pattern

```php
class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}
    
    public function createUser(CreateUserDto $dto): User
    {
        $this->validateCreateUser($dto);
        
        $user = new User();
        $user->setName($dto->name);
        $user->setEmail($dto->email);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $user;
    }
    
    private function validateCreateUser(CreateUserDto $dto): void
    {
        // Validation logic
    }
}
```

### 2. Repository Pattern

```php
// Don't access EntityManager directly in controllers
class UserController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}
    
    public function show(int $id): User
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new UserNotFoundException();
        }
        return $user;
    }
}
```

### 3. Transaction Boundaries

```php
// Good - clear transaction boundaries
public function transferBalance(int $fromUserId, int $toUserId, float $amount): void
{
    try {
        $this->entityManager->beginTransaction();
        
        $fromUser = $this->userRepository->find($fromUserId);
        $toUser = $this->userRepository->find($toUserId);
        
        $fromUser->subtractBalance($amount);
        $toUser->addBalance($amount);
        
        $this->entityManager->commit();
        $this->entityManager->flush();
        
    } catch (Exception $e) {
        $this->entityManager->rollback();
        throw $e;
    }
}
```

## Next Steps

- [Repositories](repositories.md) - Learn repository patterns
- [Change Tracking](change-tracking.md) - Understand change detection
- [Query Builder](query-builder.md) - Build complex queries
