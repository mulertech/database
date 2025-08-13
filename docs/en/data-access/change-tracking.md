# Change Tracking

Understanding how MulerTech Database automatically detects and tracks changes to managed entities.

## Overview

Change tracking is a core feature that automatically monitors modifications to entity properties and generates efficient UPDATE statements. This implements the Unit of Work pattern, ensuring that only changed properties are updated in the database.

```php
// Load entity
$user = $entityManager->find(User::class, 1);

// Modify properties
$user->setEmail('new@example.com');
$user->setName('Updated Name');

// Changes are automatically detected and persisted
$entityManager->flush(); // Generates: UPDATE users SET email = ?, name = ? WHERE id = 1
```

## How Change Tracking Works

### 1. Entity State Snapshots

When an entity is loaded from the database, the Entity Manager creates a snapshot of its original state:

```php
// When entity is loaded
$user = $entityManager->find(User::class, 1);

// Internal snapshot is created:
// $originalData = [
//     'id' => 1,
//     'name' => 'John Doe',
//     'email' => 'john@example.com',
//     'active' => true
// ];
```

### 2. Change Detection

During `flush()`, the system compares current entity state with the original snapshot:

```php
// User makes changes
$user->setEmail('john.updated@example.com');
$user->setName('John Updated');

// On flush(), change detector identifies:
// Changes: [
//     'email' => ['john@example.com', 'john.updated@example.com'],
//     'name' => ['John Doe', 'John Updated']
// ]
```

### 3. SQL Generation

Only modified fields are included in the generated UPDATE statement:

```sql
-- Generated SQL includes only changed fields
UPDATE users 
SET email = 'john.updated@example.com', name = 'John Updated' 
WHERE id = 1
```

## Entity States

### Managed State

Entities loaded from the database or persisted are automatically tracked:

```php
// Entity becomes managed after loading
$user = $entityManager->find(User::class, 1);

// Or after persist + flush
$newUser = new User();
$entityManager->persist($newUser);
$entityManager->flush(); // Now managed

// Changes to managed entities are tracked
$user->setEmail('changed@example.com');
$entityManager->flush(); // UPDATE executed
```

### Detached State

Entities can be detached to stop change tracking:

```php
$user = $entityManager->find(User::class, 1);

// Detach from tracking
$entityManager->detach($user);

// Changes are no longer tracked
$user->setEmail('not-tracked@example.com');
$entityManager->flush(); // No UPDATE executed
```

### Re-attachment

Detached entities can be re-attached:

```php
// Re-attach entity
$managedUser = $entityManager->merge($user);

// Changes are now tracked again
$managedUser->setName('Tracked Again');
$entityManager->flush(); // UPDATE executed
```

## Change Detection Process

### Automatic Detection

The most common and recommended approach:

```php
class UserService
{
    public function updateUserProfile(int $userId, array $data): User
    {
        $user = $this->entityManager->find(User::class, $userId);
        
        if (!$user) {
            throw new UserNotFoundException();
        }

        // Make changes to managed entity
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }
        
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }

        // Changes are automatically detected
        $this->entityManager->flush();
        
        return $user;
    }
}
```

### Manual Change Notification

For performance-critical scenarios, you can notify the Entity Manager of specific changes:

```php
// Not typically needed - automatic detection is preferred
$user = $entityManager->find(User::class, 1);
$user->setEmail('new@example.com');

// Manual notification (rarely needed)
$entityManager->getUnitOfWork()->scheduleForUpdate($user);
$entityManager->flush();
```

## Performance Considerations

### Batch Operations

For large datasets, batch operations with periodic clearing improve performance:

```php
public function updateManyUsers(array $updates): void
{
    $batchSize = 100;
    $count = 0;

    foreach ($updates as $userId => $data) {
        $user = $this->entityManager->find(User::class, $userId);
        
        if ($user) {
            $user->setName($data['name']);
            $user->setEmail($data['email']);
        }

        if (++$count % $batchSize === 0) {
            $this->entityManager->flush(); // Execute batch
            $this->entityManager->clear(); // Clear memory and stop tracking
        }
    }

    // Flush remaining entities
    $this->entityManager->flush();
}
```

### Selective Loading

Load only entities that will be modified:

```php
// Good - load specific entity for update
public function updateUserEmail(int $userId, string $email): void
{
    $user = $this->entityManager->find(User::class, $userId);
    $user->setEmail($email);
    $this->entityManager->flush();
}

// Avoid - loading all entities when only some will be updated
public function updateSomeUserEmails(array $emailUpdates): void
{
    $allUsers = $this->entityManager->getRepository(User::class)->findAll();
    
    foreach ($allUsers as $user) {
        if (isset($emailUpdates[$user->getId()])) {
            $user->setEmail($emailUpdates[$user->getId()]);
        }
    }
    
    $this->entityManager->flush(); // Many entities tracked unnecessarily
}
```

## Change Tracking Configuration

### Enabling/Disabling for Specific Entities

```php
// Temporarily disable change tracking
$user = $entityManager->find(User::class, 1);
$entityManager->detach($user);

// Make changes without tracking
$user->setTempField('temporary value');

// Re-enable tracking when needed
$managedUser = $entityManager->merge($user);
$managedUser->setEmail('tracked@example.com');
$entityManager->flush();
```

### Memory Management

```php
public function processLargeDataset(): void
{
    $offset = 0;
    $limit = 1000;
    
    while (true) {
        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder()
            ->limit($limit)
            ->offset($offset)
            ->getResult();
        
        if (empty($users)) {
            break;
        }
        
        foreach ($users as $user) {
            $user->setLastProcessed(new DateTime());
        }
        
        $this->entityManager->flush();
        
        // Important: Clear to free memory and stop tracking
        $this->entityManager->clear();
        
        $offset += $limit;
    }
}
```

## Advanced Change Detection

### Property-Level Validation

```php
class User
{
    private string $email;
    
    public function setEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->email = $email;
    }
}

// Validation happens during property setting
$user = $entityManager->find(User::class, 1);
$user->setEmail('invalid-email'); // Throws exception before tracking
```

### Change Logging

```php
class AuditableUser extends User
{
    private array $changeLog = [];
    
    public function setEmail(string $email): void
    {
        $oldEmail = $this->email ?? null;
        parent::setEmail($email);
        
        if ($oldEmail !== $email) {
            $this->changeLog[] = [
                'field' => 'email',
                'old_value' => $oldEmail,
                'new_value' => $email,
                'changed_at' => new DateTime()
            ];
        }
    }
    
    public function getChangeLog(): array
    {
        return $this->changeLog;
    }
}
```

### Custom Change Detection

```php
class VersionedEntity
{
    private int $version = 0;
    private DateTime $updatedAt;
    
    public function __construct()
    {
        $this->updatedAt = new DateTime();
    }
    
    public function markAsChanged(): void
    {
        $this->version++;
        $this->updatedAt = new DateTime();
    }
}

// In service layer
public function updateVersionedEntity(VersionedEntity $entity, array $data): void
{
    // Apply changes
    foreach ($data as $field => $value) {
        $setter = 'set' . ucfirst($field);
        if (method_exists($entity, $setter)) {
            $entity->$setter($value);
        }
    }
    
    // Mark as changed for version tracking
    $entity->markAsChanged();
    
    $this->entityManager->flush();
}
```

## Debugging Change Tracking

### Inspecting Tracked Changes

```php
class ChangeTrackingDebugger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}
    
    public function debugEntityChanges(object $entity): array
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        
        // Check if entity is managed
        if (!$unitOfWork->isInIdentityMap($entity)) {
            return ['status' => 'not_managed'];
        }
        
        // Get original data
        $originalData = $unitOfWork->getOriginalEntityData($entity);
        
        // Get current data
        $currentData = $this->extractEntityData($entity);
        
        // Detect changes
        $changes = [];
        foreach ($currentData as $field => $value) {
            if (!array_key_exists($field, $originalData) || $originalData[$field] !== $value) {
                $changes[$field] = [
                    'old' => $originalData[$field] ?? null,
                    'new' => $value
                ];
            }
        }
        
        return [
            'status' => 'managed',
            'has_changes' => !empty($changes),
            'changes' => $changes,
            'original_data' => $originalData,
            'current_data' => $currentData
        ];
    }
    
    private function extractEntityData(object $entity): array
    {
        // Extract current entity data using reflection
        $reflection = new ReflectionClass($entity);
        $data = [];
        
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $data[$property->getName()] = $property->getValue($entity);
        }
        
        return $data;
    }
}
```

### Change Tracking Events

```php
class ChangeTrackingEventListener
{
    public function preUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        $changeSet = $event->getChangeSet();
        
        // Log all changes
        foreach ($changeSet as $field => $change) {
            error_log(sprintf(
                'Entity %s field %s changed from %s to %s',
                get_class($entity),
                $field,
                $change[0] ?? 'null',
                $change[1] ?? 'null'
            ));
        }
    }
    
    public function postUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        // Trigger post-update business logic
        if ($entity instanceof User) {
            // Send notification, update cache, etc.
        }
    }
}
```

## Common Pitfalls

### 1. Modifying Detached Entities

```php
// Wrong - entity is detached
$user = $entityManager->find(User::class, 1);
$entityManager->clear(); // Detaches all entities
$user->setEmail('new@example.com');
$entityManager->flush(); // No UPDATE - changes ignored

// Correct - keep entity managed
$user = $entityManager->find(User::class, 1);
$user->setEmail('new@example.com');
$entityManager->flush(); // UPDATE executed
```

### 2. Forgetting to Call flush()

```php
// Wrong - changes not persisted
$user = $entityManager->find(User::class, 1);
$user->setEmail('new@example.com');
// Missing flush() - changes lost

// Correct - persist changes
$user = $entityManager->find(User::class, 1);
$user->setEmail('new@example.com');
$entityManager->flush(); // Changes saved
```

### 3. Memory Leaks in Batch Operations

```php
// Wrong - memory leak with large datasets
foreach ($largeDataset as $data) {
    $entity = new SomeEntity($data);
    $entityManager->persist($entity);
}
$entityManager->flush(); // Out of memory

// Correct - batch with clearing
foreach ($largeDataset as $i => $data) {
    $entity = new SomeEntity($data);
    $entityManager->persist($entity);
    
    if ($i % 100 === 0) {
        $entityManager->flush();
        $entityManager->clear(); // Free memory
    }
}
$entityManager->flush();
```

## Best Practices

### 1. Use Service Layer

```php
class UserService
{
    public function updateUser(int $userId, UpdateUserDto $dto): User
    {
        $user = $this->entityManager->find(User::class, $userId);
        
        if (!$user) {
            throw new UserNotFoundException();
        }
        
        // Apply changes through domain methods
        $user->changeName($dto->name);
        $user->changeEmail($dto->email);
        
        // Automatic change tracking
        $this->entityManager->flush();
        
        return $user;
    }
}
```

### 2. Encapsulate Business Logic

```php
class User
{
    public function changeEmail(string $newEmail): void
    {
        // Business validation
        if ($this->email === $newEmail) {
            return; // No change needed
        }
        
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException();
        }
        
        $this->email = $newEmail;
        $this->emailChangedAt = new DateTime();
    }
}
```

### 3. Handle Concurrent Updates

```php
class OptimisticLockingUser
{
    private int $version = 0;
    
    public function incrementVersion(): void
    {
        $this->version++;
    }
}

// In service
public function updateUserWithVersionCheck(int $userId, int $expectedVersion, array $data): User
{
    $user = $this->entityManager->find(User::class, $userId);
    
    if ($user->getVersion() !== $expectedVersion) {
        throw new OptimisticLockException('Entity was modified by another process');
    }
    
    // Apply changes
    $user->setName($data['name']);
    $user->incrementVersion();
    
    $this->entityManager->flush();
    
    return $user;
}
```

## Next Steps

- [Events](events.md) - Handle entity lifecycle events
- [Entity Manager](entity-manager.md) - Master entity management
- [Migrations](../schema-migrations/migrations.md) - Handle schema changes
