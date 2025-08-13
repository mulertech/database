# Système d'Événements

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Types d'Événements](#types-dévénements)
- [Event Dispatcher](#event-dispatcher)
- [Listeners et Subscribers](#listeners-et-subscribers)
- [Événements d'Entités](#événements-dentités)
- [Événements Personnalisés](#événements-personnalisés)
- [Événements Asynchrones](#événements-asynchrones)
- [Performance et Optimisation](#performance-et-optimisation)

## Vue d'ensemble

Le système d'événements de MulerTech Database permet de réagir aux opérations de l'ORM et d'implémenter des comportements transversaux comme l'audit, la validation, ou la synchronisation.

### Principe de Base

```php
<?php
use MulerTech\Database\Event\EventDispatcher;
use MulerTech\Database\Event\PrePersistEvent;

// Configuration de l'event dispatcher
$eventDispatcher = new EventDispatcher();

// Ajout d'un listener
$eventDispatcher->addListener(PrePersistEvent::class, function(PrePersistEvent $event) {
    $entity = $event->getEntity();
    if ($entity instanceof TimestampableInterface) {
        $entity->updateTimestamps();
    }
});

// L'événement sera déclenché automatiquement lors du persist
$emEngine = new EmEngine($driver, $eventDispatcher);
$user = new User();
$emEngine->persist($user); // Déclenche PrePersistEvent
```

## Types d'Événements

### Événements du Cycle de Vie

```php
<?php
use MulerTech\Database\Event\AbstractEntityEvent;

// Événements avant opération
class PrePersistEvent extends AbstractEntityEvent
{
    public function getName(): string
    {
        return 'entity.pre_persist';
    }
}

class PreUpdateEvent extends AbstractEntityEvent
{
    public function getName(): string
    {
        return 'entity.pre_update';
    }
    
    public function getChangeSet(): array
    {
        return $this->getData()['changeSet'] ?? [];
    }
}

class PreRemoveEvent extends AbstractEntityEvent
{
    public function getName(): string
    {
        return 'entity.pre_remove';
    }
}

class PreFlushEvent extends AbstractEntityEvent
{
    public function getName(): string
    {
        return 'entity.pre_flush';
    }
    
    public function getAllChangeSets(): array
    {
        return $this->getData()['changeSets'] ?? [];
    }
}

// Événements après opération
class PostPersistEvent extends AbstractEntityEvent
{
    public function getName(): string
    {
        return 'entity.post_persist';
    }
}

class PostUpdateEvent extends AbstractEntityEvent
{
    public function getName(): string
    {
        return 'entity.post_update';
    }
}

class PostRemoveEvent extends AbstractEntityEvent
{
    public function getName(): string
    {
        return 'entity.post_remove';
    }
}

class PostFlushEvent extends AbstractEntityEvent
{
    public function getName(): string
    {
        return 'entity.post_flush';
    }
}
```

### Événements de Transition d'État

```php
<?php
class StateTransitionEvent extends AbstractEntityEvent
{
    public function __construct(
        private object $entity,
        private string $fromState,
        private string $toState,
        private string $transition
    ) {}

    public function getName(): string
    {
        return 'entity.state_transition';
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): string
    {
        return $this->toState;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }
}

// Usage dans une entité
class Order
{
    private string $status = 'pending';
    
    public function confirm(): void
    {
        $oldStatus = $this->status;
        $this->status = 'confirmed';
        
        // Déclencher l'événement
        EventDispatcher::getInstance()->dispatch(
            new StateTransitionEvent($this, $oldStatus, 'confirmed', 'confirm')
        );
    }
}
```

## Event Dispatcher

### Implementation de Base

```php
<?php
use MulerTech\Database\Event\EventDispatcher;

class EventDispatcher
{
    private array $listeners = [];
    private array $subscribers = [];
    private bool $stopped = false;

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority][] = $listener;
        krsort($this->listeners[$eventName]); // Tri par priorité décroissante
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $priority => $listeners) {
            foreach ($listeners as $key => $registeredListener) {
                if ($registeredListener === $listener) {
                    unset($this->listeners[$eventName][$priority][$key]);
                    break 2;
                }
            }
        }
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->subscribers[] = $subscriber;
        
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->addListener($eventName, [$subscriber, $params]);
            } elseif (is_array($params)) {
                $method = $params[0];
                $priority = $params[1] ?? 0;
                $this->addListener($eventName, [$subscriber, $method], $priority);
            }
        }
    }

    public function dispatch(AbstractEntityEvent $event): AbstractEntityEvent
    {
        $eventName = $event->getName();
        
        if (!isset($this->listeners[$eventName])) {
            return $event;
        }

        $this->stopped = false;

        foreach ($this->listeners[$eventName] as $priority => $listeners) {
            if ($this->stopped) {
                break;
            }

            foreach ($listeners as $listener) {
                if ($this->stopped) {
                    break;
                }

                call_user_func($listener, $event, $eventName, $this);
            }
        }

        return $event;
    }

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    public function hasListeners(string $eventName = null): bool
    {
        if ($eventName !== null) {
            return !empty($this->listeners[$eventName]);
        }

        foreach ($this->listeners as $listeners) {
            if (!empty($listeners)) {
                return true;
            }
        }

        return false;
    }

    public function getListeners(string $eventName = null): array
    {
        if ($eventName !== null) {
            return $this->listeners[$eventName] ?? [];
        }

        return $this->listeners;
    }
}
```

### Event Dispatcher Avancé

```php
<?php
class AdvancedEventDispatcher extends EventDispatcher
{
    private array $eventHistory = [];
    private bool $recordHistory = false;
    private array $middleware = [];

    public function enableHistory(): void
    {
        $this->recordHistory = true;
    }

    public function disableHistory(): void
    {
        $this->recordHistory = false;
        $this->eventHistory = [];
    }

    public function getEventHistory(): array
    {
        return $this->eventHistory;
    }

    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function dispatch(AbstractEntityEvent $event): AbstractEntityEvent
    {
        if ($this->recordHistory) {
            $this->eventHistory[] = [
                'event' => get_class($event),
                'name' => $event->getName(),
                'timestamp' => microtime(true),
                'entity' => get_class($event->getEntity())
            ];
        }

        // Exécuter les middlewares
        foreach ($this->middleware as $middleware) {
            $event = call_user_func($middleware, $event, $this);
            if ($event === null) {
                return $event; // Middleware a arrêté l'événement
            }
        }

        return parent::dispatch($event);
    }
}

// Middleware exemple
class ValidationMiddleware
{
    public function __invoke(AbstractEntityEvent $event, EventDispatcher $dispatcher): ?AbstractEntityEvent
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof ValidatableInterface) {
            $errors = $entity->validate();
            if (!empty($errors)) {
                // Arrêter la propagation si validation échoue
                throw new ValidationException('Entity validation failed', $errors);
            }
        }
        
        return $event;
    }
}
```

## Listeners et Subscribers

### Event Listeners

```php
<?php
class TimestampListener
{
    public function onPrePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof TimestampableInterface) {
            $now = new \DateTime();
            $entity->setCreatedAt($now);
            $entity->setUpdatedAt($now);
        }
    }

    public function onPreUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof TimestampableInterface) {
            $entity->setUpdatedAt(new \DateTime());
        }
    }
}

class SlugListener
{
    public function onPrePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof SluggableInterface && empty($entity->getSlug())) {
            $slug = $this->generateSlug($entity->getTitle());
            $entity->setSlug($slug);
        }
    }

    public function onPreUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        $changeSet = $event->getChangeSet();
        
        if ($entity instanceof SluggableInterface && isset($changeSet['title'])) {
            $slug = $this->generateSlug($entity->getTitle());
            $entity->setSlug($slug);
        }
    }

    private function generateSlug(string $title): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }
}
```

### Event Subscribers

```php
<?php
interface EventSubscriberInterface
{
    public static function getSubscribedEvents(): array;
}

class AuditSubscriber implements EventSubscriberInterface
{
    private AuditLogRepository $auditRepository;

    public function __construct(AuditLogRepository $auditRepository)
    {
        $this->auditRepository = $auditRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PrePersistEvent::class => ['onPrePersist', 10],
            PreUpdateEvent::class => ['onPreUpdate', 10],
            PreRemoveEvent::class => ['onPreRemove', 10],
        ];
    }

    public function onPrePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof AuditableInterface) {
            $this->createAuditLog($entity, 'CREATE');
        }
    }

    public function onPreUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        $changeSet = $event->getChangeSet();
        
        if ($entity instanceof AuditableInterface) {
            $this->createAuditLog($entity, 'UPDATE', $changeSet);
        }
    }

    public function onPreRemove(PreRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($entity instanceof AuditableInterface) {
            $this->createAuditLog($entity, 'DELETE');
        }
    }

    private function createAuditLog(object $entity, string $action, array $changeSet = []): void
    {
        $auditLog = new AuditLog();
        $auditLog->setEntityClass(get_class($entity));
        $auditLog->setEntityId($entity->getId());
        $auditLog->setAction($action);
        $auditLog->setChanges($changeSet);
        $auditLog->setUserId($this->getCurrentUserId());
        $auditLog->setTimestamp(new \DateTime());
        
        $this->auditRepository->save($auditLog);
    }

    private function getCurrentUserId(): ?int
    {
        // Récupérer l'ID de l'utilisateur connecté
        return $_SESSION['user_id'] ?? null;
    }
}

class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostUpdateEvent::class => 'onPostUpdate',
            PostRemoveEvent::class => 'onPostRemove',
        ];
    }

    public function onPostUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        $this->invalidateEntityCache($entity);
    }

    public function onPostRemove(PostRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        $this->invalidateEntityCache($entity);
    }

    private function invalidateEntityCache(object $entity): void
    {
        $entityClass = get_class($entity);
        $entityId = $entity->getId();
        
        // Invalider le cache spécifique à l'entité
        $this->cache->delete("{$entityClass}_{$entityId}");
        
        // Invalider les caches de listes liés
        $this->cache->deleteByPattern("{$entityClass}_list_*");
    }
}
```

## Événements d'Entités

### Intégration dans l'EmEngine

```php
<?php
class EmEngine
{
    private EventDispatcher $eventDispatcher;

    public function __construct(DatabaseDriverInterface $driver, EventDispatcher $eventDispatcher = null)
    {
        $this->driver = $driver;
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
    }

    public function persist(object $entity): void
    {
        // Déclencher PrePersistEvent
        $event = new PrePersistEvent($entity);
        $this->eventDispatcher->dispatch($event);

        // Logique de persist
        $this->changeDetector->markAsNew($entity);
    }

    public function flush(): void
    {
        $changeSets = $this->changeDetector->getAllChangeSets();

        // Déclencher PreFlushEvent
        $preFlushEvent = new PreFlushEvent(null, ['changeSets' => $changeSets]);
        $this->eventDispatcher->dispatch($preFlushEvent);

        foreach ($changeSets as $changeSet) {
            $entity = $changeSet->getEntity();
            $operation = $changeSet->getOperation();

            switch ($operation) {
                case 'INSERT':
                    $this->processInsert($entity);
                    $this->eventDispatcher->dispatch(new PostPersistEvent($entity));
                    break;

                case 'UPDATE':
                    $this->eventDispatcher->dispatch(new PreUpdateEvent($entity, ['changeSet' => $changeSet->getChanges()]));
                    $this->processUpdate($entity, $changeSet);
                    $this->eventDispatcher->dispatch(new PostUpdateEvent($entity));
                    break;

                case 'DELETE':
                    $this->eventDispatcher->dispatch(new PreRemoveEvent($entity));
                    $this->processDelete($entity);
                    $this->eventDispatcher->dispatch(new PostRemoveEvent($entity));
                    break;
            }
        }

        // Déclencher PostFlushEvent
        $postFlushEvent = new PostFlushEvent(null, ['changeSets' => $changeSets]);
        $this->eventDispatcher->dispatch($postFlushEvent);

        $this->changeDetector->clear();
    }
}
```

### Événements Conditionnels

```php
<?php
class ConditionalEventDispatcher extends EventDispatcher
{
    private array $conditions = [];

    public function addCondition(string $eventName, callable $condition): void
    {
        $this->conditions[$eventName][] = $condition;
    }

    public function dispatch(AbstractEntityEvent $event): AbstractEntityEvent
    {
        $eventName = $event->getName();

        // Vérifier les conditions
        if (isset($this->conditions[$eventName])) {
            foreach ($this->conditions[$eventName] as $condition) {
                if (!call_user_func($condition, $event)) {
                    return $event; // Condition non remplie, ne pas déclencher
                }
            }
        }

        return parent::dispatch($event);
    }
}

// Usage
$dispatcher = new ConditionalEventDispatcher();

// N'auditer que les entités importantes
$dispatcher->addCondition(PreUpdateEvent::class, function(PreUpdateEvent $event) {
    return $event->getEntity() instanceof ImportantEntity;
});

// Ne pas envoyer d'email en mode test
$dispatcher->addCondition('user.registered', function($event) {
    return $_ENV['APP_ENV'] !== 'test';
});
```

## Événements Personnalisés

### Création d'Événements Métier

```php
<?php
class UserRegisteredEvent extends AbstractEntityEvent
{
    public function __construct(
        private User $user,
        private string $registrationMethod = 'web'
    ) {}

    public function getName(): string
    {
        return 'user.registered';
    }

    public function getEntity(): User
    {
        return $this->user;
    }

    public function getRegistrationMethod(): string
    {
        return $this->registrationMethod;
    }
}

class OrderCompletedEvent extends AbstractEntityEvent
{
    public function __construct(
        private Order $order,
        private float $totalAmount
    ) {}

    public function getName(): string
    {
        return 'order.completed';
    }

    public function getEntity(): Order
    {
        return $this->order;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }
}

// Service métier
class UserService
{
    private EventDispatcher $eventDispatcher;

    public function registerUser(array $userData, string $method = 'web'): User
    {
        $user = new User();
        $user->setEmail($userData['email']);
        $user->setName($userData['name']);
        
        $this->emEngine->persist($user);
        $this->emEngine->flush();

        // Déclencher l'événement métier
        $event = new UserRegisteredEvent($user, $method);
        $this->eventDispatcher->dispatch($event);

        return $user;
    }
}
```

### Listeners Métier

```php
<?php
class WelcomeEmailListener
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $user = $event->getEntity();
        
        $this->emailService->sendWelcomeEmail(
            $user->getEmail(),
            $user->getName(),
            $event->getRegistrationMethod()
        );
    }
}

class LoyaltyPointsListener
{
    private LoyaltyService $loyaltyService;

    public function onOrderCompleted(OrderCompletedEvent $event): void
    {
        $order = $event->getEntity();
        $amount = $event->getTotalAmount();
        
        $points = (int) floor($amount / 10); // 1 point par 10€
        $this->loyaltyService->addPoints($order->getCustomer(), $points);
    }
}

class NotificationListener
{
    private NotificationService $notificationService;

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $user = $event->getEntity();
        
        $this->notificationService->sendAdminNotification(
            "New user registered: {$user->getEmail()}"
        );
    }

    public function onOrderCompleted(OrderCompletedEvent $event): void
    {
        $order = $event->getEntity();
        
        $this->notificationService->sendOrderConfirmation($order);
    }
}
```

## Événements Asynchrones

### Queue d'Événements

```php
<?php
interface EventQueueInterface
{
    public function enqueue(AbstractEntityEvent $event): void;
    public function dequeue(): ?AbstractEntityEvent;
    public function isEmpty(): bool;
}

class AsyncEventDispatcher extends EventDispatcher
{
    private EventQueueInterface $eventQueue;
    private array $asyncEvents = [];

    public function __construct(EventQueueInterface $eventQueue)
    {
        $this->eventQueue = $eventQueue;
    }

    public function markAsAsync(string $eventName): void
    {
        $this->asyncEvents[] = $eventName;
    }

    public function dispatch(AbstractEntityEvent $event): AbstractEntityEvent
    {
        $eventName = $event->getName();

        if (in_array($eventName, $this->asyncEvents)) {
            $this->eventQueue->enqueue($event);
            return $event;
        }

        return parent::dispatch($event);
    }
}

class EventWorker
{
    private EventQueueInterface $eventQueue;
    private EventDispatcher $syncDispatcher;

    public function __construct(EventQueueInterface $eventQueue, EventDispatcher $syncDispatcher)
    {
        $this->eventQueue = $eventQueue;
        $this->syncDispatcher = $syncDispatcher;
    }

    public function processEvents(): void
    {
        while (!$this->eventQueue->isEmpty()) {
            $event = $this->eventQueue->dequeue();
            
            if ($event !== null) {
                try {
                    $this->syncDispatcher->dispatch($event);
                } catch (\Exception $e) {
                    // Logger l'erreur et continuer
                    error_log("Error processing async event: " . $e->getMessage());
                }
            }
        }
    }
}

// CLI worker
// php bin/event-worker.php
$worker = new EventWorker($eventQueue, $syncDispatcher);
$worker->processEvents();
```

### Événements avec Retry

```php
<?php
class RetryableEvent extends AbstractEntityEvent
{
    private int $retryCount = 0;
    private int $maxRetries = 3;

    public function incrementRetry(): void
    {
        $this->retryCount++;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function hasRetriesLeft(): bool
    {
        return $this->retryCount < $this->maxRetries;
    }
}

class RetryableEventDispatcher extends EventDispatcher
{
    private EventQueueInterface $retryQueue;

    public function dispatch(AbstractEntityEvent $event): AbstractEntityEvent
    {
        try {
            return parent::dispatch($event);
        } catch (\Exception $e) {
            if ($event instanceof RetryableEvent && $event->hasRetriesLeft()) {
                $event->incrementRetry();
                $this->retryQueue->enqueue($event);
            } else {
                throw $e;
            }
            
            return $event;
        }
    }
}
```

## Performance et Optimisation

### Lazy Event Loading

```php
<?php
class LazyEventDispatcher extends EventDispatcher
{
    private array $lazyListeners = [];

    public function addLazyListener(string $eventName, string $className, string $method, int $priority = 0): void
    {
        $this->lazyListeners[$eventName][$priority][] = [$className, $method];
    }

    public function dispatch(AbstractEntityEvent $event): AbstractEntityEvent
    {
        $eventName = $event->getName();

        // Charger les listeners lazy si nécessaire
        if (isset($this->lazyListeners[$eventName])) {
            foreach ($this->lazyListeners[$eventName] as $priority => $listeners) {
                foreach ($listeners as [$className, $method]) {
                    $instance = new $className(); // Ou utiliser un container DI
                    $this->addListener($eventName, [$instance, $method], $priority);
                }
            }
            unset($this->lazyListeners[$eventName]);
        }

        return parent::dispatch($event);
    }
}
```

### Event Profiling

```php
<?php
class ProfiledEventDispatcher extends EventDispatcher
{
    private array $executionTimes = [];

    public function dispatch(AbstractEntityEvent $event): AbstractEntityEvent
    {
        $eventName = $event->getName();
        $startTime = microtime(true);

        $result = parent::dispatch($event);

        $executionTime = microtime(true) - $startTime;
        
        if (!isset($this->executionTimes[$eventName])) {
            $this->executionTimes[$eventName] = [];
        }
        
        $this->executionTimes[$eventName][] = $executionTime;

        return $result;
    }

    public function getProfileData(): array
    {
        $profile = [];

        foreach ($this->executionTimes as $eventName => $times) {
            $profile[$eventName] = [
                'count' => count($times),
                'total_time' => array_sum($times),
                'average_time' => array_sum($times) / count($times),
                'max_time' => max($times),
                'min_time' => min($times)
            ];
        }

        return $profile;
    }

    public function printProfile(): void
    {
        $profile = $this->getProfileData();

        echo "Event Performance Profile:\n";
        echo "==========================\n";

        foreach ($profile as $eventName => $data) {
            echo sprintf(
                "%s: %d calls, %.4fs total, %.4fs avg, %.4fs max\n",
                $eventName,
                $data['count'],
                $data['total_time'],
                $data['average_time'],
                $data['max_time']
            );
        }
    }
}
```

---

**Navigation :**
- [← Suivi des Modifications](change-tracking.md)
- [→ Système de Cache](caching.md)
- [↑ ORM](../README.md)
