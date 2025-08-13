# Events

Complete guide to the event system in MulerTech Database for handling entity lifecycle events.

## Overview

The event system allows you to hook into entity lifecycle events to execute custom logic at specific points during entity operations. This enables clean separation of concerns and extensible application architecture.

```php
// Events are automatically triggered during entity operations
$user = new User();
$user->setName('John Doe');

$entityManager->persist($user);  // Triggers PrePersistEvent
$entityManager->flush();         // Triggers PostPersistEvent
```

## Available Events

### Entity Lifecycle Events

**PrePersistEvent**
- Triggered before a new entity is inserted into the database
- Useful for setting timestamps, generating IDs, validation

**PostPersistEvent**
- Triggered after a new entity is successfully inserted
- Useful for logging, cache invalidation, notifications

**PreUpdateEvent**
- Triggered before an existing entity is updated
- Provides access to change set (old vs new values)
- Useful for validation, audit logging

**PostUpdateEvent**
- Triggered after an entity is successfully updated
- Useful for cache updates, notifications

**PreRemoveEvent**
- Triggered before an entity is deleted
- Useful for validation, dependency checking

**PostRemoveEvent**
- Triggered after an entity is successfully deleted
- Useful for cleanup, cache invalidation

**PreFlushEvent**
- Triggered before any database operations during flush
- Useful for batch validation, pre-processing

**PostFlushEvent**
- Triggered after all database operations are completed
- Useful for cleanup, final processing

## Event Listener Implementation

### Basic Event Listener

```php
use MulerTech\Database\Event\{
    PrePersistEvent,
    PostPersistEvent,
    PreUpdateEvent,
    PostUpdateEvent,
    PreRemoveEvent,
    PostRemoveEvent
};

class UserEventListener
{
    public function prePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Set timestamps
            $entity->setCreatedAt(new DateTime());
            $entity->setUpdatedAt(new DateTime());
            
            // Generate UUID if not set
            if (!$entity->getId()) {
                $entity->setId(Uuid::uuid4()->toString());
            }
        }
    }
    
    public function postPersist(PostPersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Send welcome email
            $this->emailService->sendWelcomeEmail($entity);
            
            // Log user creation
            $this->logger->info('New user created', [
                'user_id' => $entity->getId(),
                'email' => $entity->getEmail()
            ]);
        }
    }
    
    public function preUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Update timestamp
            $entity->setUpdatedAt(new DateTime());
            
            // Check for email changes
            if ($event->hasChangedField('email')) {
                $oldEmail = $event->getOldValue('email');
                $newEmail = $event->getNewValue('email');
                
                // Send confirmation email to new address
                $this->emailService->sendEmailChangeConfirmation($entity, $newEmail);
            }
        }
    }
    
    public function postUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Clear user cache
            $this->cache->delete("user_{$entity->getId()}");
            
            // Update search index
            $this->searchService->updateUserIndex($entity);
        }
    }
    
    public function preRemove(PreRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Check for dependencies
            $orderCount = $this->orderRepository->countByUser($entity);
            if ($orderCount > 0) {
                throw new CannotDeleteUserException('User has existing orders');
            }
            
            // Archive user data
            $this->archiveService->archiveUser($entity);
        }
    }
    
    public function postRemove(PostRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Clear all user-related caches
            $this->cache->clearByPattern("user_{$entity->getId()}_*");
            
            // Remove from search index
            $this->searchService->removeUserFromIndex($entity->getId());
            
            // Log deletion
            $this->logger->info('User deleted', [
                'user_id' => $entity->getId(),
                'deleted_by' => $this->getCurrentUserId()
            ]);
        }
    }
}
```

### Registering Event Listeners

```php
// Register event listeners with the entity manager
$eventDispatcher = $entityManager->getEventDispatcher();

$userEventListener = new UserEventListener(
    $emailService,
    $logger,
    $cache,
    $searchService,
    $orderRepository,
    $archiveService
);

$eventDispatcher->addListener('prePersist', [$userEventListener, 'prePersist']);
$eventDispatcher->addListener('postPersist', [$userEventListener, 'postPersist']);
$eventDispatcher->addListener('preUpdate', [$userEventListener, 'preUpdate']);
$eventDispatcher->addListener('postUpdate', [$userEventListener, 'postUpdate']);
$eventDispatcher->addListener('preRemove', [$userEventListener, 'preRemove']);
$eventDispatcher->addListener('postRemove', [$userEventListener, 'postRemove']);
```

## Specialized Event Listeners

### Audit Trail Listener

```php
class AuditTrailListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityContextInterface $security
    ) {}
    
    public function postPersist(PostPersistEvent $event): void
    {
        $this->createAuditEntry($event->getEntity(), 'CREATE');
    }
    
    public function postUpdate(PostUpdateEvent $event): void
    {
        $this->createAuditEntry($event->getEntity(), 'UPDATE', $event->getChangeSet());
    }
    
    public function postRemove(PostRemoveEvent $event): void
    {
        $this->createAuditEntry($event->getEntity(), 'DELETE');
    }
    
    private function createAuditEntry(object $entity, string $action, array $changeSet = []): void
    {
        $audit = new AuditLog();
        $audit->setEntityType(get_class($entity));
        $audit->setEntityId($this->getEntityId($entity));
        $audit->setAction($action);
        $audit->setChangeSet($changeSet);
        $audit->setUserId($this->security->getCurrentUserId());
        $audit->setCreatedAt(new DateTime());
        
        $this->entityManager->persist($audit);
        // Note: Don't flush here as it would cause infinite recursion
    }
    
    private function getEntityId(object $entity): mixed
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }
        
        // Use reflection to find ID property
        $reflection = new ReflectionClass($entity);
        foreach ($reflection->getProperties() as $property) {
            if ($property->getName() === 'id') {
                $property->setAccessible(true);
                return $property->getValue($entity);
            }
        }
        
        return null;
    }
}
```

### Cache Management Listener

```php
class CacheInvalidationListener
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {}
    
    public function postUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            $this->invalidateUserCaches($entity);
        } elseif ($entity instanceof Product) {
            $this->invalidateProductCaches($entity);
        }
    }
    
    public function postRemove(PostRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            $this->invalidateUserCaches($entity);
        } elseif ($entity instanceof Product) {
            $this->invalidateProductCaches($entity);
        }
    }
    
    private function invalidateUserCaches(User $user): void
    {
        $this->cache->delete("user_{$user->getId()}");
        $this->cache->delete("user_profile_{$user->getId()}");
        $this->cache->clearByPattern("user_orders_{$user->getId()}_*");
    }
    
    private function invalidateProductCaches(Product $product): void
    {
        $this->cache->delete("product_{$product->getId()}");
        $this->cache->delete("category_products_{$product->getCategoryId()}");
        $this->cache->clearByPattern("product_search_*");
    }
}
```

### Notification Listener

```php
class NotificationListener
{
    public function __construct(
        private readonly NotificationServiceInterface $notificationService,
        private readonly EmailServiceInterface $emailService
    ) {}
    
    public function postPersist(PostPersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof Order) {
            // Send order confirmation
            $this->emailService->sendOrderConfirmation($entity);
            
            // Notify admins of new order
            $this->notificationService->notifyAdmins('New order received', [
                'order_id' => $entity->getId(),
                'customer' => $entity->getCustomer()->getName(),
                'total' => $entity->getTotal()
            ]);
        }
    }
    
    public function preUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof Order && $event->hasChangedField('status')) {
            $oldStatus = $event->getOldValue('status');
            $newStatus = $event->getNewValue('status');
            
            // Send status update email
            $this->emailService->sendOrderStatusUpdate($entity, $oldStatus, $newStatus);
            
            // Special handling for shipped orders
            if ($newStatus === 'shipped') {
                $this->emailService->sendShippingNotification($entity);
            }
        }
    }
}
```

## Event Priority and Ordering

### Priority-based Event Handling

```php
class PriorityEventListener
{
    // High priority - runs first
    public static function getSubscribedEvents(): array
    {
        return [
            'preUpdate' => ['onPreUpdate', 100], // Priority 100
            'postUpdate' => ['onPostUpdate', 100]
        ];
    }
    
    public function onPreUpdate(PreUpdateEvent $event): void
    {
        // Critical validation that must run first
        if ($event->getEntity() instanceof User) {
            $this->validateCriticalChanges($event);
        }
    }
    
    public function onPostUpdate(PostUpdateEvent $event): void
    {
        // High priority post-processing
        if ($event->getEntity() instanceof User) {
            $this->updateSecurityContext($event->getEntity());
        }
    }
}

class LowPriorityEventListener
{
    // Low priority - runs last
    public static function getSubscribedEvents(): array
    {
        return [
            'postUpdate' => ['onPostUpdate', -100] // Priority -100
        ];
    }
    
    public function onPostUpdate(PostUpdateEvent $event): void
    {
        // Non-critical operations that can run last
        if ($event->getEntity() instanceof User) {
            $this->updateAnalytics($event->getEntity());
        }
    }
}
```

## Conditional Event Handling

### Environment-based Events

```php
class EnvironmentAwareEventListener
{
    public function __construct(
        private readonly string $environment
    ) {}
    
    public function postPersist(PostPersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Only send emails in production
            if ($this->environment === 'production') {
                $this->emailService->sendWelcomeEmail($entity);
            }
            
            // Always log in all environments
            $this->logger->info('User created', [
                'user_id' => $entity->getId(),
                'environment' => $this->environment
            ]);
        }
    }
}
```

### Feature Flag Events

```php
class FeatureFlagEventListener
{
    public function __construct(
        private readonly FeatureFlagServiceInterface $featureFlags
    ) {}
    
    public function postUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof Product) {
            // Only update search index if feature is enabled
            if ($this->featureFlags->isEnabled('elasticsearch_integration')) {
                $this->searchService->updateProductIndex($entity);
            }
            
            // Only send notifications if feature is enabled
            if ($this->featureFlags->isEnabled('product_update_notifications')) {
                $this->notificationService->notifyProductUpdate($entity);
            }
        }
    }
}
```

## Error Handling in Events

### Exception Handling

```php
class SafeEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}
    
    public function postPersist(PostPersistEvent $event): void
    {
        try {
            $entity = $event->getEntity();
            
            if ($entity instanceof User) {
                $this->processUserCreation($entity);
            }
            
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            $this->logger->error('Event processing failed', [
                'event' => 'postPersist',
                'entity' => get_class($event->getEntity()),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Optionally re-throw for critical events
            if ($this->isCriticalEvent($e)) {
                throw $e;
            }
        }
    }
    
    private function isCriticalEvent(Exception $e): bool
    {
        // Define which exceptions should fail the main operation
        return $e instanceof SecurityException || 
               $e instanceof DataIntegrityException;
    }
}
```

### Graceful Degradation

```php
class ResilientEventListener
{
    public function postUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof Product) {
            // Try cache update
            try {
                $this->cache->set("product_{$entity->getId()}", $entity);
            } catch (CacheException $e) {
                $this->logger->warning('Cache update failed', ['error' => $e->getMessage()]);
                // Continue without cache
            }
            
            // Try search index update
            try {
                $this->searchService->updateIndex($entity);
            } catch (SearchException $e) {
                $this->logger->warning('Search index update failed', ['error' => $e->getMessage()]);
                // Queue for retry
                $this->queueService->push('search_update', [
                    'entity_type' => get_class($entity),
                    'entity_id' => $entity->getId()
                ]);
            }
        }
    }
}
```

## Testing Events

### Event Testing

```php
class UserEventListenerTest extends PHPUnit\Framework\TestCase
{
    private UserEventListener $listener;
    private MockObject $emailService;
    private MockObject $logger;
    
    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->listener = new UserEventListener($this->emailService, $this->logger);
    }
    
    public function testPrePersistSetsTimestamps(): void
    {
        $user = new User();
        $event = new PrePersistEvent($user, $this->createMock(EntityManagerInterface::class));
        
        $this->listener->prePersist($event);
        
        $this->assertInstanceOf(DateTime::class, $user->getCreatedAt());
        $this->assertInstanceOf(DateTime::class, $user->getUpdatedAt());
    }
    
    public function testPostPersistSendsWelcomeEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        
        $event = new PostPersistEvent($user, $this->createMock(EntityManagerInterface::class));
        
        $this->emailService
            ->expects($this->once())
            ->method('sendWelcomeEmail')
            ->with($user);
        
        $this->listener->postPersist($event);
    }
    
    public function testPreUpdateDetectsEmailChange(): void
    {
        $user = new User();
        $user->setEmail('old@example.com');
        
        $changeSet = ['email' => ['old@example.com', 'new@example.com']];
        $event = $this->createMock(PreUpdateEvent::class);
        
        $event->method('getEntity')->willReturn($user);
        $event->method('hasChangedField')->with('email')->willReturn(true);
        $event->method('getOldValue')->with('email')->willReturn('old@example.com');
        $event->method('getNewValue')->with('email')->willReturn('new@example.com');
        
        $this->emailService
            ->expects($this->once())
            ->method('sendEmailChangeConfirmation')
            ->with($user, 'new@example.com');
        
        $this->listener->preUpdate($event);
    }
}
```

## Best Practices

### 1. Keep Events Lightweight

```php
// Good - lightweight event processing
public function postPersist(PostPersistEvent $event): void
{
    if ($event->getEntity() instanceof User) {
        // Queue heavy operations
        $this->messageQueue->push('user.welcome', [
            'user_id' => $event->getEntity()->getId()
        ]);
    }
}

// Avoid - heavy processing in events
public function postPersist(PostPersistEvent $event): void
{
    if ($event->getEntity() instanceof User) {
        // Don't do this - too slow
        $this->generateUserReport($event->getEntity());
        $this->sendMultipleEmails($event->getEntity());
        $this->updateExternalSystems($event->getEntity());
    }
}
```

### 2. Handle Failures Gracefully

```php
public function postUpdate(PostUpdateEvent $event): void
{
    try {
        $this->criticalOperation($event->getEntity());
    } catch (CriticalException $e) {
        // Re-throw critical exceptions
        throw $e;
    } catch (Exception $e) {
        // Log non-critical failures
        $this->logger->error('Non-critical event operation failed', [
            'error' => $e->getMessage()
        ]);
    }
}
```

### 3. Use Dependency Injection

```php
class UserEventListener
{
    public function __construct(
        private readonly EmailServiceInterface $emailService,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache
    ) {}
    
    // Event methods...
}

// Register with DI container
$container->register(UserEventListener::class)
    ->addArgument($emailService)
    ->addArgument($logger)
    ->addArgument($cache);
```

## Complete Example

```php
class ComprehensiveUserEventListener
{
    public function __construct(
        private readonly EmailServiceInterface $emailService,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly SecurityServiceInterface $security,
        private readonly AuditServiceInterface $audit,
        private readonly string $environment
    ) {}
    
    public function prePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            // Set timestamps
            $entity->setCreatedAt(new DateTime());
            $entity->setUpdatedAt(new DateTime());
            
            // Validate user data
            $this->validateUser($entity);
            
            // Set default values
            if (!$entity->getRole()) {
                $entity->setRole('user');
            }
        }
    }
    
    public function postPersist(PostPersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof User) {
            try {
                // Send welcome email (production only)
                if ($this->environment === 'production') {
                    $this->emailService->sendWelcomeEmail($entity);
                }
                
                // Create audit log
                $this->audit->logUserCreation($entity, $this->security->getCurrentUserId());
                
                // Clear relevant caches
                $this->cache->clearByPattern('user_stats_*');
                
            } catch (Exception $e) {
                $this->logger->error('Post-persist processing failed', [
                    'user_id' => $entity->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    private function validateUser(User $user): void
    {
        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }
        
        if (strlen($user->getName()) < 2) {
            throw new InvalidArgumentException('Name must be at least 2 characters');
        }
    }
}
```

## Next Steps

- [Schema Migrations](../schema-migrations/migrations.md) - Handle database schema changes
- [Migration Tools](../schema-migrations/migration-tools.md) - Use migration CLI tools
- [Entity Manager](entity-manager.md) - Master entity lifecycle management
