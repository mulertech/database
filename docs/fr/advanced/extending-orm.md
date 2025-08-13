# Étendre l'ORM

Guide pour étendre et personnaliser MulerTech Database ORM selon vos besoins spécifiques.

## Table des Matières
- [Architecture extensible](#architecture-extensible)
- [Extension de l'EntityManager](#extension-de-lentitymanager)
- [Repositories personnalisés avancés](#repositories-personnalisés-avancés)
- [Événements et hooks personnalisés](#événements-et-hooks-personnalisés)
- [Middleware et intercepteurs](#middleware-et-intercepteurs)
- [Extension du QueryBuilder](#extension-du-querybuilder)

## Architecture extensible

### Points d'extension de l'ORM

MulerTech Database ORM offre plusieurs points d'extension :

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Extension;

interface ExtensionInterface
{
    public function getName(): string;
    
    public function getVersion(): string;
    
    public function install(ExtensionManager $manager): void;
    
    public function uninstall(ExtensionManager $manager): void;
    
    public function getConfigurationSchema(): array;
}
```

### Gestionnaire d'extensions

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Extension;

use MulerTech\Database\EntityManager;
use MulerTech\Database\Exception\ExtensionException;

class ExtensionManager
{
    private EntityManager $entityManager;
    private array $extensions = [];
    private array $hooks = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function registerExtension(ExtensionInterface $extension): void
    {
        $name = $extension->getName();
        
        if (isset($this->extensions[$name])) {
            throw new ExtensionException("Extension '{$name}' is already registered");
        }

        $this->extensions[$name] = $extension;
        $extension->install($this);
    }

    public function unregisterExtension(string $name): void
    {
        if (!isset($this->extensions[$name])) {
            throw new ExtensionException("Extension '{$name}' is not registered");
        }

        $extension = $this->extensions[$name];
        $extension->uninstall($this);
        
        unset($this->extensions[$name]);
    }

    public function getExtension(string $name): ExtensionInterface
    {
        if (!isset($this->extensions[$name])) {
            throw new ExtensionException("Extension '{$name}' is not registered");
        }

        return $this->extensions[$name];
    }

    public function hasExtension(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    public function getRegisteredExtensions(): array
    {
        return array_keys($this->extensions);
    }

    public function addHook(string $event, callable $callback, int $priority = 0): void
    {
        if (!isset($this->hooks[$event])) {
            $this->hooks[$event] = [];
        }

        $this->hooks[$event][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Trier par priorité (plus élevé = exécuté en premier)
        usort($this->hooks[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    public function executeHooks(string $event, array $args = []): array
    {
        if (!isset($this->hooks[$event])) {
            return $args;
        }

        foreach ($this->hooks[$event] as $hook) {
            $args = call_user_func_array($hook['callback'], $args) ?: $args;
        }

        return $args;
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }
}
```

### Exemple d'extension : Cache avancé

```php
<?php

declare(strict_types=1);

namespace App\Extension;

use MulerTech\Database\Extension\ExtensionInterface;
use MulerTech\Database\Extension\ExtensionManager;
use MulerTech\Database\Event\PostLoadEvent;
use MulerTech\Database\Event\PrePersistEvent;

class AdvancedCacheExtension implements ExtensionInterface
{
    private array $config;
    private $cacheAdapter;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'ttl' => 3600,
            'namespace' => 'mulertech_orm',
            'strategy' => 'write_through',
        ], $config);
    }

    public function getName(): string
    {
        return 'advanced_cache';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function install(ExtensionManager $manager): void
    {
        // Initialiser le cache adapter
        $this->initializeCacheAdapter();

        // Enregistrer les hooks d'événements
        $manager->addHook('entity.post_load', [$this, 'onEntityLoaded'], 100);
        $manager->addHook('entity.pre_persist', [$this, 'onEntityPersist'], 100);
        $manager->addHook('entity.pre_remove', [$this, 'onEntityRemove'], 100);

        // Enregistrer les hooks de query
        $manager->addHook('query.pre_execute', [$this, 'onQueryPreExecute'], 50);
        $manager->addHook('query.post_execute', [$this, 'onQueryPostExecute'], 50);
    }

    public function uninstall(ExtensionManager $manager): void
    {
        // Nettoyer le cache
        if ($this->cacheAdapter) {
            $this->cacheAdapter->clear();
        }
    }

    public function getConfigurationSchema(): array
    {
        return [
            'ttl' => ['type' => 'integer', 'default' => 3600],
            'namespace' => ['type' => 'string', 'default' => 'mulertech_orm'],
            'strategy' => ['type' => 'string', 'enum' => ['write_through', 'write_back', 'write_around']],
        ];
    }

    public function onEntityLoaded(PostLoadEvent $event): void
    {
        $entity = $event->getEntity();
        $cacheKey = $this->generateEntityCacheKey($entity);
        
        // Stocker l'entité en cache après chargement
        $this->cacheAdapter->set($cacheKey, $entity, $this->config['ttl']);
    }

    public function onEntityPersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->config['strategy'] === 'write_through') {
            $cacheKey = $this->generateEntityCacheKey($entity);
            $this->cacheAdapter->set($cacheKey, $entity, $this->config['ttl']);
        }
    }

    public function onEntityRemove($entity): void
    {
        $cacheKey = $this->generateEntityCacheKey($entity);
        $this->cacheAdapter->delete($cacheKey);
    }

    public function onQueryPreExecute(string $dql, array $parameters): ?array
    {
        $cacheKey = $this->generateQueryCacheKey($dql, $parameters);
        $cachedResult = $this->cacheAdapter->get($cacheKey);
        
        if ($cachedResult !== null) {
            // Retourner le résultat mis en cache pour court-circuiter l'exécution
            return ['result' => $cachedResult, 'from_cache' => true];
        }
        
        return null;
    }

    public function onQueryPostExecute(string $dql, array $parameters, $result): void
    {
        $cacheKey = $this->generateQueryCacheKey($dql, $parameters);
        $this->cacheAdapter->set($cacheKey, $result, $this->config['ttl']);
    }

    private function initializeCacheAdapter(): void
    {
        // Initialiser votre adapter de cache préféré
        $this->cacheAdapter = new \Redis();
        $this->cacheAdapter->connect('127.0.0.1', 6379);
    }

    private function generateEntityCacheKey($entity): string
    {
        $className = get_class($entity);
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;
        
        return $this->config['namespace'] . ':entity:' . $className . ':' . $id;
    }

    private function generateQueryCacheKey(string $dql, array $parameters): string
    {
        $key = $dql . serialize($parameters);
        return $this->config['namespace'] . ':query:' . md5($key);
    }
}
```

## Extension de l'EntityManager

### EntityManager personnalisé

```php
<?php

declare(strict_types=1);

namespace App\ORM;

use MulerTech\Database\EntityManager as BaseEntityManager;
use MulerTech\Database\Configuration\Configuration;
use MulerTech\Database\Extension\ExtensionManager;

class CustomEntityManager extends BaseEntityManager
{
    private ExtensionManager $extensionManager;
    private array $customBehaviors = [];

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        $this->extensionManager = new ExtensionManager($this);
        $this->initializeCustomBehaviors();
    }

    public function getExtensionManager(): ExtensionManager
    {
        return $this->extensionManager;
    }

    public function addCustomBehavior(string $name, callable $behavior): void
    {
        $this->customBehaviors[$name] = $behavior;
    }

    public function executeCustomBehavior(string $name, ...$args)
    {
        if (!isset($this->customBehaviors[$name])) {
            throw new \InvalidArgumentException("Custom behavior '{$name}' not found");
        }

        return call_user_func_array($this->customBehaviors[$name], $args);
    }

    public function persist(object $entity): void
    {
        // Exécuter les hooks avant persistance
        $this->extensionManager->executeHooks('entity.pre_persist', [$entity]);
        
        parent::persist($entity);
        
        // Exécuter les hooks après persistance
        $this->extensionManager->executeHooks('entity.post_persist', [$entity]);
    }

    public function remove(object $entity): void
    {
        // Exécuter les hooks avant suppression
        $this->extensionManager->executeHooks('entity.pre_remove', [$entity]);
        
        parent::remove($entity);
        
        // Exécuter les hooks après suppression
        $this->extensionManager->executeHooks('entity.post_remove', [$entity]);
    }

    public function flush(): void
    {
        // Exécuter les hooks avant flush
        $this->extensionManager->executeHooks('entity.pre_flush', []);
        
        parent::flush();
        
        // Exécuter les hooks après flush
        $this->extensionManager->executeHooks('entity.post_flush', []);
    }

    // Méthodes d'extension personnalisées
    public function findWithCache(string $entityClass, $id, int $ttl = 3600)
    {
        if ($this->extensionManager->hasExtension('advanced_cache')) {
            $cache = $this->extensionManager->getExtension('advanced_cache');
            return $cache->findEntity($entityClass, $id, $ttl);
        }

        return $this->find($entityClass, $id);
    }

    public function batchInsert(array $entities, int $batchSize = 100): void
    {
        $count = 0;
        
        foreach ($entities as $entity) {
            $this->persist($entity);
            $count++;
            
            if ($count % $batchSize === 0) {
                $this->flush();
                $this->clear();
            }
        }
        
        if ($count % $batchSize !== 0) {
            $this->flush();
        }
    }

    public function findByExample(object $example): array
    {
        $metadata = $this->getClassMetadata(get_class($example));
        $qb = $this->createQueryBuilder();
        $qb->select('e')->from(get_class($example), 'e');
        
        foreach ($metadata->getFieldNames() as $fieldName) {
            $getter = 'get' . ucfirst($fieldName);
            if (method_exists($example, $getter)) {
                $value = $example->$getter();
                if ($value !== null) {
                    $qb->andWhere("e.{$fieldName} = :{$fieldName}")
                       ->setParameter($fieldName, $value);
                }
            }
        }
        
        return $qb->getQuery()->getResult();
    }

    private function initializeCustomBehaviors(): void
    {
        // Comportement de soft delete
        $this->addCustomBehavior('softDelete', function($entity) {
            if (method_exists($entity, 'setDeletedAt')) {
                $entity->setDeletedAt(new \DateTime());
                $this->persist($entity);
                return true;
            }
            return false;
        });

        // Comportement d'audit automatique
        $this->addCustomBehavior('audit', function($entity, string $action) {
            $auditLog = new \App\Entity\AuditLog();
            $auditLog->setEntityClass(get_class($entity));
            $auditLog->setEntityId($entity->getId());
            $auditLog->setAction($action);
            $auditLog->setCreatedAt(new \DateTime());
            $this->persist($auditLog);
        });

        // Comportement de versioning
        $this->addCustomBehavior('version', function($entity) {
            if (method_exists($entity, 'incrementVersion')) {
                $entity->incrementVersion();
                return true;
            }
            return false;
        });
    }
}
```

## Repositories personnalisés avancés

### Repository avec fonctionnalités avancées

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use MulerTech\Database\Repository\EntityRepository;
use MulerTech\Database\Query\QueryBuilder;

abstract class AdvancedRepository extends EntityRepository
{
    // Cache des requêtes au niveau repository
    private array $queryCache = [];
    private bool $cacheEnabled = true;

    public function enableCache(): void
    {
        $this->cacheEnabled = true;
    }

    public function disableCache(): void
    {
        $this->cacheEnabled = false;
        $this->queryCache = [];
    }

    public function findWithCriteria(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $cacheKey = md5(serialize(func_get_args()));
        
        if ($this->cacheEnabled && isset($this->queryCache[$cacheKey])) {
            return $this->queryCache[$cacheKey];
        }

        $qb = $this->createQueryBuilder('e');
        
        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $qb->andWhere("e.{$field} IN (:{$field})")
                   ->setParameter($field, $value);
            } else {
                $qb->andWhere("e.{$field} = :{$field}")
                   ->setParameter($field, $value);
            }
        }

        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy("e.{$field}", $direction);
            }
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        $result = $qb->getQuery()->getResult();
        
        if ($this->cacheEnabled) {
            $this->queryCache[$cacheKey] = $result;
        }

        return $result;
    }

    public function findBySearchTerms(array $searchFields, string $term): array
    {
        $qb = $this->createQueryBuilder('e');
        
        $orConditions = [];
        foreach ($searchFields as $field) {
            $orConditions[] = "e.{$field} LIKE :term";
        }
        
        $qb->where(implode(' OR ', $orConditions))
           ->setParameter('term', "%{$term}%");

        return $qb->getQuery()->getResult();
    }

    public function countByCriteria(array $criteria): int
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('COUNT(e.id)');
        
        foreach ($criteria as $field => $value) {
            $qb->andWhere("e.{$field} = :{$field}")
               ->setParameter($field, $value);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findPaginated(int $page, int $perPage, array $criteria = [], ?array $orderBy = null): array
    {
        $offset = ($page - 1) * $perPage;
        
        $entities = $this->findWithCriteria($criteria, $orderBy, $perPage, $offset);
        $total = $this->countByCriteria($criteria);
        
        return [
            'data' => $entities,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1,
            ]
        ];
    }

    public function bulkUpdate(array $criteria, array $updateData): int
    {
        $qb = $this->createQueryBuilder('e');
        $qb->update();

        foreach ($updateData as $field => $value) {
            $qb->set("e.{$field}", ":{$field}")
               ->setParameter($field, $value);
        }

        foreach ($criteria as $field => $value) {
            $qb->andWhere("e.{$field} = :{$field}_criteria")
               ->setParameter("{$field}_criteria", $value);
        }

        return $qb->getQuery()->execute();
    }

    public function bulkDelete(array $criteria): int
    {
        $qb = $this->createQueryBuilder('e');
        $qb->delete();

        foreach ($criteria as $field => $value) {
            $qb->andWhere("e.{$field} = :{$field}")
               ->setParameter($field, $value);
        }

        return $qb->getQuery()->execute();
    }

    protected function addDateRangeFilter(QueryBuilder $qb, string $field, ?\DateTime $from = null, ?\DateTime $to = null): QueryBuilder
    {
        if ($from) {
            $qb->andWhere("e.{$field} >= :from")
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere("e.{$field} <= :to")
               ->setParameter('to', $to);
        }

        return $qb;
    }

    protected function addFullTextSearch(QueryBuilder $qb, array $fields, string $term): QueryBuilder
    {
        $searchConditions = [];
        
        foreach ($fields as $field) {
            $searchConditions[] = "MATCH(e.{$field}) AGAINST (:term IN BOOLEAN MODE)";
        }

        if (!empty($searchConditions)) {
            $qb->andWhere(implode(' OR ', $searchConditions))
               ->setParameter('term', $term);
        }

        return $qb;
    }
}
```

### Repository avec fonctionnalités métier

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use DateTime;

class UserRepository extends AdvancedRepository
{
    public function findActiveUsers(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function findRecentlyJoined(int $days = 30): array
    {
        $since = new DateTime("-{$days} days");
        
        return $this->createQueryBuilder('u')
            ->where('u.createdAt >= :since')
            ->andWhere('u.active = :active')
            ->setParameter('since', $since)
            ->setParameter('active', true)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTopContributors(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('u', 'COUNT(p.id) as post_count')
            ->leftJoin('u.posts', 'p')
            ->where('u.active = :active')
            ->groupBy('u.id')
            ->having('post_count > 0')
            ->orderBy('post_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findUsersWithoutPosts(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.posts', 'p')
            ->where('p.id IS NULL')
            ->andWhere('u.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function getUserStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');
        
        $total = (int) $qb->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = (int) $qb->select('COUNT(u.id)')
            ->where('u.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $recentlyJoined = (int) $qb->select('COUNT(u.id)')
            ->where('u.createdAt >= :since')
            ->setParameter('since', new DateTime('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'recently_joined' => $recentlyJoined,
            'activity_rate' => $total > 0 ? ($active / $total) * 100 : 0,
        ];
    }

    public function findByEmailDomain(string $domain): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :domain')
            ->setParameter('domain', "%@{$domain}")
            ->getQuery()
            ->getResult();
    }

    public function archiveInactiveUsers(int $inactiveDays = 365): int
    {
        $cutoffDate = new DateTime("-{$inactiveDays} days");
        
        return $this->createQueryBuilder('u')
            ->update()
            ->set('u.archived', ':archived')
            ->where('u.lastLoginAt < :cutoff OR u.lastLoginAt IS NULL')
            ->andWhere('u.createdAt < :cutoff')
            ->andWhere('u.archived = :notArchived')
            ->setParameter('archived', true)
            ->setParameter('notArchived', false)
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
```

## Événements et hooks personnalisés

### Système d'événements avancé

```php
<?php

declare(strict_types=1);

namespace App\Event;

use MulerTech\Database\Event\AbstractEntityEvent;

class CustomEntityEvent extends AbstractEntityEvent
{
    private array $metadata;
    private array $context;

    public function __construct(object $entity, array $metadata = [], array $context = [])
    {
        parent::__construct($entity);
        $this->metadata = $metadata;
        $this->context = $context;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function addContextData(string $key, $value): void
    {
        $this->context[$key] = $value;
    }
}

class BusinessLogicEvent
{
    private string $eventType;
    private array $data;
    private bool $propagationStopped = false;

    public function __construct(string $eventType, array $data = [])
    {
        $this->eventType = $eventType;
        $this->data = $data;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
```

### Listeners d'événements métier

```php
<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Event\CustomEntityEvent;
use App\Service\EmailService;
use App\Service\AuditService;

class UserEventListener
{
    private EmailService $emailService;
    private AuditService $auditService;

    public function __construct(EmailService $emailService, AuditService $auditService)
    {
        $this->emailService = $emailService;
        $this->auditService = $auditService;
    }

    public function onUserCreated(CustomEntityEvent $event): void
    {
        $user = $event->getEntity();
        
        if (!$user instanceof User) {
            return;
        }

        // Envoyer un email de bienvenue
        $this->emailService->sendWelcomeEmail($user);

        // Logger la création
        $this->auditService->logUserAction('user_created', $user);

        // Ajouter des données de contexte
        $event->addContextData('welcome_email_sent', true);
        $event->addContextData('audit_logged', true);
    }

    public function onUserUpdated(CustomEntityEvent $event): void
    {
        $user = $event->getEntity();
        
        if (!$user instanceof User) {
            return;
        }

        $changeSet = $event->getMetadata()['changeSet'] ?? [];
        
        // Si l'email a changé, envoyer une notification
        if (isset($changeSet['email'])) {
            $this->emailService->sendEmailChangeNotification($user, $changeSet['email']);
        }

        // Si le statut actif a changé
        if (isset($changeSet['active'])) {
            $action = $changeSet['active'][1] ? 'user_activated' : 'user_deactivated';
            $this->auditService->logUserAction($action, $user);
        }
    }

    public function onUserDeleted(CustomEntityEvent $event): void
    {
        $user = $event->getEntity();
        
        if (!$user instanceof User) {
            return;
        }

        // Anonymiser les données avant suppression complète
        $this->anonymizeUserData($user);
        
        // Logger la suppression
        $this->auditService->logUserAction('user_deleted', $user);
    }

    private function anonymizeUserData(User $user): void
    {
        $user->setEmail('deleted_' . $user->getId() . '@example.com');
        $user->setName('Utilisateur supprimé');
        // Garder l'ID pour l'intégrité référentielle
    }
}
```

## Middleware et intercepteurs

### Système de middleware pour requêtes

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

interface QueryMiddlewareInterface
{
    public function handle(string $sql, array $parameters, callable $next);
}

class QueryLoggingMiddleware implements QueryMiddlewareInterface
{
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function handle(string $sql, array $parameters, callable $next)
    {
        $startTime = microtime(true);
        
        $this->logger->debug('Executing query', [
            'sql' => $sql,
            'parameters' => $parameters
        ]);

        $result = $next($sql, $parameters);

        $executionTime = microtime(true) - $startTime;
        
        $this->logger->info('Query executed', [
            'execution_time' => $executionTime,
            'affected_rows' => $result->rowCount() ?? 0
        ]);

        return $result;
    }
}

class QueryCachingMiddleware implements QueryMiddlewareInterface
{
    private $cache;
    private int $ttl;

    public function __construct($cache, int $ttl = 3600)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    public function handle(string $sql, array $parameters, callable $next)
    {
        // Ne mettre en cache que les requêtes SELECT
        if (!$this->isSelectQuery($sql)) {
            return $next($sql, $parameters);
        }

        $cacheKey = $this->generateCacheKey($sql, $parameters);
        $cachedResult = $this->cache->get($cacheKey);

        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $result = $next($sql, $parameters);
        
        $this->cache->set($cacheKey, $result, $this->ttl);

        return $result;
    }

    private function isSelectQuery(string $sql): bool
    {
        return stripos(trim($sql), 'SELECT') === 0;
    }

    private function generateCacheKey(string $sql, array $parameters): string
    {
        return 'query_' . md5($sql . serialize($parameters));
    }
}

class MiddlewareStack
{
    private array $middlewares = [];

    public function add(QueryMiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function execute(string $sql, array $parameters, callable $finalHandler)
    {
        $stack = array_reduce(
            array_reverse($this->middlewares),
            function ($next, $middleware) {
                return function ($sql, $parameters) use ($middleware, $next) {
                    return $middleware->handle($sql, $parameters, $next);
                };
            },
            $finalHandler
        );

        return $stack($sql, $parameters);
    }
}
```

## Extension du QueryBuilder

### QueryBuilder avec méthodes personnalisées

```php
<?php

declare(strict_types=1);

namespace App\Query;

use MulerTech\Database\Query\QueryBuilder as BaseQueryBuilder;

class ExtendedQueryBuilder extends BaseQueryBuilder
{
    public function whereIn(string $field, array $values): self
    {
        if (empty($values)) {
            return $this->where('1 = 0'); // Condition impossible
        }

        $placeholders = [];
        foreach ($values as $index => $value) {
            $placeholder = "value_{$index}";
            $placeholders[] = ":{$placeholder}";
            $this->setParameter($placeholder, $value);
        }

        return $this->andWhere("{$field} IN (" . implode(', ', $placeholders) . ")");
    }

    public function whereNotIn(string $field, array $values): self
    {
        if (empty($values)) {
            return $this; // Aucune condition
        }

        $placeholders = [];
        foreach ($values as $index => $value) {
            $placeholder = "value_{$index}";
            $placeholders[] = ":{$placeholder}";
            $this->setParameter($placeholder, $value);
        }

        return $this->andWhere("{$field} NOT IN (" . implode(', ', $placeholders) . ")");
    }

    public function whereBetween(string $field, $min, $max): self
    {
        return $this->andWhere("{$field} BETWEEN :min AND :max")
                   ->setParameter('min', $min)
                   ->setParameter('max', $max);
    }

    public function whereDate(string $field, string $date): self
    {
        return $this->andWhere("DATE({$field}) = :date")
                   ->setParameter('date', $date);
    }

    public function whereYear(string $field, int $year): self
    {
        return $this->andWhere("YEAR({$field}) = :year")
                   ->setParameter('year', $year);
    }

    public function whereMonth(string $field, int $month): self
    {
        return $this->andWhere("MONTH({$field}) = :month")
                   ->setParameter('month', $month);
    }

    public function fullTextSearch(array $fields, string $term): self
    {
        $fieldList = implode(', ', $fields);
        return $this->andWhere("MATCH({$fieldList}) AGAINST (:searchTerm IN BOOLEAN MODE)")
                   ->setParameter('searchTerm', $term);
    }

    public function orderByRand(): self
    {
        return $this->orderBy('RAND()');
    }

    public function orderByDistance(string $latField, string $lngField, float $lat, float $lng): self
    {
        $distanceExpression = "SQRT(POW(69.1 * ({$latField} - :lat), 2) + POW(69.1 * (:lng - {$lngField}) * COS({$latField} / 57.3), 2))";
        
        return $this->addSelect("{$distanceExpression} AS distance")
                   ->orderBy('distance')
                   ->setParameter('lat', $lat)
                   ->setParameter('lng', $lng);
    }

    public function withCTE(string $name, string $query): self
    {
        // Support pour Common Table Expressions (MySQL 8.0+)
        $currentDQL = $this->getDQL();
        $newDQL = "WITH {$name} AS ({$query}) " . $currentDQL;
        
        return $this->setDQL($newDQL);
    }

    public function paginate(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Cloner le query builder pour le count
        $countQb = clone $this;
        $countQb->select('COUNT(' . $this->getRootAliases()[0] . '.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        
        // Appliquer la pagination
        $this->setFirstResult($offset)
             ->setMaxResults($perPage);
        
        $results = $this->getQuery()->getResult();
        
        return [
            'data' => $results,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1,
            ]
        ];
    }
}
```

---

**Voir aussi :**
- [Types personnalisés](custom-types.md)
- [Système de plugins](plugins.md)
- [Architecture interne](internals.md)
