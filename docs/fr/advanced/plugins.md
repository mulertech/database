# Système de Plugins

Guide pour créer et utiliser des plugins dans MulerTech Database ORM.

## Table des Matières
- [Architecture des plugins](#architecture-des-plugins)
- [Création de plugins](#création-de-plugins)
- [Plugins intégrés](#plugins-intégrés)
- [Gestion des plugins](#gestion-des-plugins)
- [Configuration avancée](#configuration-avancée)
- [Exemples de plugins](#exemples-de-plugins)

## Architecture des plugins

### Interface plugin de base

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Plugin;

use MulerTech\Database\EntityManager;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface PluginInterface
{
    /**
     * @return string
     */
    public function getName(): string;
    
    /**
     * @return string
     */
    public function getVersion(): string;
    
    /**
     * @return array<string>
     */
    public function getDependencies(): array;
    
    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function install(EntityManager $entityManager): void;
    
    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function uninstall(EntityManager $entityManager): void;
    
    /**
     * @return bool
     */
    public function isEnabled(): bool;
    
    /**
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void;
    
    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfiguration(): array;
}
```

### Plugin abstrait de base

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Plugin;

use MulerTech\Database\EntityManager;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected bool $enabled = true;
    protected array $configuration = [];

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(array $configuration = [])
    {
        $this->configuration = array_merge($this->getDefaultConfiguration(), $configuration);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfiguration(): array
    {
        return [];
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setConfig(string $key, mixed $value): void
    {
        $this->configuration[$key] = $value;
    }
}
```

### Gestionnaire de plugins

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Plugin;

use MulerTech\Database\EntityManager;
use MulerTech\Database\Exception\PluginException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PluginManager
{
    /** @var array<string, PluginInterface> */
    private array $plugins = [];
    
    /** @var array<string, bool> */
    private array $loadedPlugins = [];
    
    private EntityManager $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param PluginInterface $plugin
     * @return void
     * @throws PluginException
     */
    public function registerPlugin(PluginInterface $plugin): void
    {
        $name = $plugin->getName();
        
        if (isset($this->plugins[$name])) {
            throw new PluginException("Plugin '{$name}' is already registered");
        }

        $this->validateDependencies($plugin);
        $this->plugins[$name] = $plugin;
    }

    /**
     * @param string $name
     * @return void
     * @throws PluginException
     */
    public function loadPlugin(string $name): void
    {
        if (!isset($this->plugins[$name])) {
            throw new PluginException("Plugin '{$name}' is not registered");
        }

        if ($this->isPluginLoaded($name)) {
            return;
        }

        $plugin = $this->plugins[$name];
        
        if (!$plugin->isEnabled()) {
            throw new PluginException("Plugin '{$name}' is disabled");
        }

        // Load dependencies first
        foreach ($plugin->getDependencies() as $dependency) {
            $this->loadPlugin($dependency);
        }

        $plugin->install($this->entityManager);
        $this->loadedPlugins[$name] = true;
    }

    /**
     * @param string $name
     * @return void
     * @throws PluginException
     */
    public function unloadPlugin(string $name): void
    {
        if (!$this->isPluginLoaded($name)) {
            return;
        }

        // Check if other plugins depend on this one
        $dependents = $this->findDependentPlugins($name);
        if (!empty($dependents)) {
            throw new PluginException(
                "Cannot unload plugin '{$name}' as it's required by: " . implode(', ', $dependents)
            );
        }

        $plugin = $this->plugins[$name];
        $plugin->uninstall($this->entityManager);
        unset($this->loadedPlugins[$name]);
    }

    /**
     * @return void
     */
    public function loadAllPlugins(): void
    {
        foreach ($this->plugins as $name => $plugin) {
            if ($plugin->isEnabled() && !$this->isPluginLoaded($name)) {
                $this->loadPlugin($name);
            }
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function isPluginLoaded(string $name): bool
    {
        return isset($this->loadedPlugins[$name]);
    }

    /**
     * @param string $name
     * @return PluginInterface|null
     */
    public function getPlugin(string $name): ?PluginInterface
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * @return array<string, PluginInterface>
     */
    public function getRegisteredPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * @return array<string>
     */
    public function getLoadedPlugins(): array
    {
        return array_keys($this->loadedPlugins);
    }

    /**
     * @param PluginInterface $plugin
     * @return void
     * @throws PluginException
     */
    private function validateDependencies(PluginInterface $plugin): void
    {
        foreach ($plugin->getDependencies() as $dependency) {
            if (!isset($this->plugins[$dependency])) {
                throw new PluginException(
                    "Plugin '{$plugin->getName()}' requires '{$dependency}' which is not registered"
                );
            }
        }
    }

    /**
     * @param string $pluginName
     * @return array<string>
     */
    private function findDependentPlugins(string $pluginName): array
    {
        $dependents = [];
        
        foreach ($this->plugins as $name => $plugin) {
            if (in_array($pluginName, $plugin->getDependencies(), true)) {
                $dependents[] = $name;
            }
        }
        
        return $dependents;
    }
}
```

## Création de plugins

### Plugin de logging

```php
<?php

declare(strict_types=1);

namespace App\Plugin;

use MulerTech\Database\Plugin\AbstractPlugin;
use MulerTech\Database\EntityManager;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PreRemoveEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use Psr\Log\LoggerInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class AuditLogPlugin extends AbstractPlugin
{
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     * @param array<string, mixed> $configuration
     */
    public function __construct(LoggerInterface $logger, array $configuration = [])
    {
        parent::__construct($configuration);
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'audit_log';
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function install(EntityManager $entityManager): void
    {
        $eventDispatcher = $entityManager->getEventDispatcher();

        if ($this->getConfig('log_create', true)) {
            $eventDispatcher->addListener('prePersist', [$this, 'onPrePersist']);
            $eventDispatcher->addListener('postPersist', [$this, 'onPostPersist']);
        }

        if ($this->getConfig('log_update', true)) {
            $eventDispatcher->addListener('preUpdate', [$this, 'onPreUpdate']);
            $eventDispatcher->addListener('postUpdate', [$this, 'onPostUpdate']);
        }

        if ($this->getConfig('log_delete', true)) {
            $eventDispatcher->addListener('preRemove', [$this, 'onPreRemove']);
            $eventDispatcher->addListener('postRemove', [$this, 'onPostRemove']);
        }
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function uninstall(EntityManager $entityManager): void
    {
        $eventDispatcher = $entityManager->getEventDispatcher();
        
        $eventDispatcher->removeListener('prePersist', [$this, 'onPrePersist']);
        $eventDispatcher->removeListener('postPersist', [$this, 'onPostPersist']);
        $eventDispatcher->removeListener('preUpdate', [$this, 'onPreUpdate']);
        $eventDispatcher->removeListener('postUpdate', [$this, 'onPostUpdate']);
        $eventDispatcher->removeListener('preRemove', [$this, 'onPreRemove']);
        $eventDispatcher->removeListener('postRemove', [$this, 'onPostRemove']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'log_create' => true,
            'log_update' => true,
            'log_delete' => true,
            'log_level' => 'info',
            'include_data' => false,
            'excluded_entities' => [],
        ];
    }

    /**
     * @param PrePersistEvent $event
     * @return void
     */
    public function onPrePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->shouldLogEntity($entity)) {
            $this->logger->info('Entity creation started', [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'action' => 'create_start',
            ]);
        }
    }

    /**
     * @param PostPersistEvent $event
     * @return void
     */
    public function onPostPersist(PostPersistEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->shouldLogEntity($entity)) {
            $logData = [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'action' => 'create_complete',
            ];

            if ($this->getConfig('include_data', false)) {
                $logData['entity_data'] = $this->serializeEntity($entity);
            }

            $this->logger->info('Entity created successfully', $logData);
        }
    }

    /**
     * @param PreUpdateEvent $event
     * @return void
     */
    public function onPreUpdate(PreUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->shouldLogEntity($entity)) {
            $logData = [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'action' => 'update_start',
                'changed_fields' => array_keys($event->getChangeSet()),
            ];

            if ($this->getConfig('include_data', false)) {
                $logData['changes'] = $event->getChangeSet();
            }

            $this->logger->info('Entity update started', $logData);
        }
    }

    /**
     * @param PostUpdateEvent $event
     * @return void
     */
    public function onPostUpdate(PostUpdateEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->shouldLogEntity($entity)) {
            $this->logger->info('Entity updated successfully', [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'action' => 'update_complete',
            ]);
        }
    }

    /**
     * @param PreRemoveEvent $event
     * @return void
     */
    public function onPreRemove(PreRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->shouldLogEntity($entity)) {
            $logData = [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'action' => 'delete_start',
            ];

            if ($this->getConfig('include_data', false)) {
                $logData['entity_data'] = $this->serializeEntity($entity);
            }

            $this->logger->info('Entity deletion started', $logData);
        }
    }

    /**
     * @param PostRemoveEvent $event
     * @return void
     */
    public function onPostRemove(PostRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        
        if ($this->shouldLogEntity($entity)) {
            $this->logger->info('Entity deleted successfully', [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'action' => 'delete_complete',
            ]);
        }
    }

    /**
     * @param object $entity
     * @return bool
     */
    private function shouldLogEntity(object $entity): bool
    {
        $excludedEntities = $this->getConfig('excluded_entities', []);
        return !in_array(get_class($entity), $excludedEntities, true);
    }

    /**
     * @param object $entity
     * @return mixed
     */
    private function getEntityId(object $entity): mixed
    {
        return method_exists($entity, 'getId') ? $entity->getId() : null;
    }

    /**
     * @param object $entity
     * @return array<string, mixed>
     */
    private function serializeEntity(object $entity): array
    {
        // Simple serialization - could be enhanced with more sophisticated methods
        $reflection = new \ReflectionClass($entity);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            // Avoid circular references
            if (is_object($value) && !$value instanceof \DateTimeInterface) {
                $value = '[Object: ' . get_class($value) . ']';
            }
            
            $data[$property->getName()] = $value;
        }

        return $data;
    }
}
```

### Plugin de cache intelligent

```php
<?php

declare(strict_types=1);

namespace App\Plugin;

use MulerTech\Database\Plugin\AbstractPlugin;
use MulerTech\Database\EntityManager;
use MulerTech\Database\Event\PostLoadEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SmartCachePlugin extends AbstractPlugin
{
    private CacheItemPoolInterface $cache;
    /** @var array<string, int> */
    private array $hitStats = [];
    /** @var array<string, int> */
    private array $missStats = [];

    /**
     * @param CacheItemPoolInterface $cache
     * @param array<string, mixed> $configuration
     */
    public function __construct(CacheItemPoolInterface $cache, array $configuration = [])
    {
        parent::__construct($configuration);
        $this->cache = $cache;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'smart_cache';
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return '2.0.0';
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function install(EntityManager $entityManager): void
    {
        $eventDispatcher = $entityManager->getEventDispatcher();

        // Hook into entity loading for caching
        $eventDispatcher->addListener('postLoad', [$this, 'onPostLoad']);
        
        // Invalidate cache on modifications
        $eventDispatcher->addListener('postPersist', [$this, 'onPostPersist']);
        $eventDispatcher->addListener('postUpdate', [$this, 'onPostUpdate']);
        $eventDispatcher->addListener('postRemove', [$this, 'onPostRemove']);

        // Override find methods to use cache
        $this->overrideFindMethods($entityManager);
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function uninstall(EntityManager $entityManager): void
    {
        $eventDispatcher = $entityManager->getEventDispatcher();
        
        $eventDispatcher->removeListener('postLoad', [$this, 'onPostLoad']);
        $eventDispatcher->removeListener('postPersist', [$this, 'onPostPersist']);
        $eventDispatcher->removeListener('postUpdate', [$this, 'onPostUpdate']);
        $eventDispatcher->removeListener('postRemove', [$this, 'onPostRemove']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'ttl' => 3600,
            'enabled_entities' => [],
            'cache_queries' => true,
            'cache_collections' => false,
            'auto_invalidate' => true,
            'statistics' => true,
        ];
    }

    /**
     * @param string $entityClass
     * @param mixed $id
     * @return object|null
     */
    public function findCached(string $entityClass, mixed $id): ?object
    {
        if (!$this->isCacheableEntity($entityClass)) {
            return null;
        }

        $cacheKey = $this->generateEntityCacheKey($entityClass, $id);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $this->recordHit($entityClass);
            return $cacheItem->get();
        }

        $this->recordMiss($entityClass);
        return null;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function cacheEntity(object $entity): void
    {
        $entityClass = get_class($entity);
        
        if (!$this->isCacheableEntity($entityClass)) {
            return;
        }

        $id = $this->getEntityId($entity);
        if ($id === null) {
            return;
        }

        $cacheKey = $this->generateEntityCacheKey($entityClass, $id);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        $cacheItem->set($entity);
        $cacheItem->expiresAfter($this->getConfig('ttl', 3600));
        
        $this->cache->save($cacheItem);
    }

    /**
     * @param PostLoadEvent $event
     * @return void
     */
    public function onPostLoad(PostLoadEvent $event): void
    {
        $this->cacheEntity($event->getEntity());
    }

    /**
     * @param PostPersistEvent $event
     * @return void
     */
    public function onPostPersist(PostPersistEvent $event): void
    {
        $this->cacheEntity($event->getEntity());
        
        if ($this->getConfig('auto_invalidate', true)) {
            $this->invalidateRelatedCaches($event->getEntity());
        }
    }

    /**
     * @param PostUpdateEvent $event
     * @return void
     */
    public function onPostUpdate(PostUpdateEvent $event): void
    {
        $this->cacheEntity($event->getEntity());
        
        if ($this->getConfig('auto_invalidate', true)) {
            $this->invalidateRelatedCaches($event->getEntity());
        }
    }

    /**
     * @param PostRemoveEvent $event
     * @return void
     */
    public function onPostRemove(PostRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        $this->invalidateEntityCache($entity);
        
        if ($this->getConfig('auto_invalidate', true)) {
            $this->invalidateRelatedCaches($entity);
        }
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function getStatistics(): array
    {
        return [
            'hits' => $this->hitStats,
            'misses' => $this->missStats,
            'hit_rate' => $this->calculateHitRate(),
        ];
    }

    /**
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache->clear();
        $this->hitStats = [];
        $this->missStats = [];
    }

    /**
     * @param string $entityClass
     * @return bool
     */
    private function isCacheableEntity(string $entityClass): bool
    {
        $enabledEntities = $this->getConfig('enabled_entities', []);
        return empty($enabledEntities) || in_array($entityClass, $enabledEntities, true);
    }

    /**
     * @param string $entityClass
     * @param mixed $id
     * @return string
     */
    private function generateEntityCacheKey(string $entityClass, mixed $id): string
    {
        return 'entity_' . str_replace('\\', '_', $entityClass) . '_' . $id;
    }

    /**
     * @param object $entity
     * @return mixed
     */
    private function getEntityId(object $entity): mixed
    {
        return method_exists($entity, 'getId') ? $entity->getId() : null;
    }

    /**
     * @param object $entity
     * @return void
     */
    private function invalidateEntityCache(object $entity): void
    {
        $id = $this->getEntityId($entity);
        if ($id !== null) {
            $cacheKey = $this->generateEntityCacheKey(get_class($entity), $id);
            $this->cache->deleteItem($cacheKey);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    private function invalidateRelatedCaches(object $entity): void
    {
        // Implement logic to invalidate related entity caches
        // This would depend on your entity relationships
    }

    /**
     * @param string $entityClass
     * @return void
     */
    private function recordHit(string $entityClass): void
    {
        if ($this->getConfig('statistics', true)) {
            $this->hitStats[$entityClass] = ($this->hitStats[$entityClass] ?? 0) + 1;
        }
    }

    /**
     * @param string $entityClass
     * @return void
     */
    private function recordMiss(string $entityClass): void
    {
        if ($this->getConfig('statistics', true)) {
            $this->missStats[$entityClass] = ($this->missStats[$entityClass] ?? 0) + 1;
        }
    }

    /**
     * @return array<string, float>
     */
    private function calculateHitRate(): array
    {
        $hitRates = [];
        
        foreach ($this->hitStats as $entityClass => $hits) {
            $misses = $this->missStats[$entityClass] ?? 0;
            $total = $hits + $misses;
            $hitRates[$entityClass] = $total > 0 ? ($hits / $total) * 100 : 0;
        }
        
        return $hitRates;
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    private function overrideFindMethods(EntityManager $entityManager): void
    {
        // This would require more complex implementation to properly override
        // EntityManager methods while maintaining compatibility
    }
}
```

## Plugins intégrés

### Plugin de validation

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Plugin\Built;

use MulerTech\Database\Plugin\AbstractPlugin;
use MulerTech\Database\EntityManager;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\Exception\ValidationException;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ValidationPlugin extends AbstractPlugin
{
    private ValidatorInterface $validator;

    /**
     * @param ValidatorInterface $validator
     * @param array<string, mixed> $configuration
     */
    public function __construct(ValidatorInterface $validator, array $configuration = [])
    {
        parent::__construct($configuration);
        $this->validator = $validator;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'validation';
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function install(EntityManager $entityManager): void
    {
        $eventDispatcher = $entityManager->getEventDispatcher();

        $eventDispatcher->addListener('prePersist', [$this, 'onPrePersist']);
        $eventDispatcher->addListener('preUpdate', [$this, 'onPreUpdate']);
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function uninstall(EntityManager $entityManager): void
    {
        $eventDispatcher = $entityManager->getEventDispatcher();
        
        $eventDispatcher->removeListener('prePersist', [$this, 'onPrePersist']);
        $eventDispatcher->removeListener('preUpdate', [$this, 'onPreUpdate']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'validate_on_persist' => true,
            'validate_on_update' => true,
            'throw_on_violation' => true,
            'validation_groups' => ['Default'],
        ];
    }

    /**
     * @param PrePersistEvent $event
     * @return void
     * @throws ValidationException
     */
    public function onPrePersist(PrePersistEvent $event): void
    {
        if ($this->getConfig('validate_on_persist', true)) {
            $this->validateEntity($event->getEntity());
        }
    }

    /**
     * @param PreUpdateEvent $event
     * @return void
     * @throws ValidationException
     */
    public function onPreUpdate(PreUpdateEvent $event): void
    {
        if ($this->getConfig('validate_on_update', true)) {
            $this->validateEntity($event->getEntity());
        }
    }

    /**
     * @param object $entity
     * @return void
     * @throws ValidationException
     */
    private function validateEntity(object $entity): void
    {
        $violations = $this->validator->validate(
            $entity,
            null,
            $this->getConfig('validation_groups', ['Default'])
        );

        if (count($violations) > 0 && $this->getConfig('throw_on_violation', true)) {
            throw new ValidationException($violations);
        }
    }
}
```

### Plugin de soft delete

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Plugin\Built;

use MulerTech\Database\Plugin\AbstractPlugin;
use MulerTech\Database\EntityManager;
use MulerTech\Database\Event\PreRemoveEvent;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SoftDeletePlugin extends AbstractPlugin
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'soft_delete';
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function install(EntityManager $entityManager): void
    {
        $eventDispatcher = $entityManager->getEventDispatcher();
        $eventDispatcher->addListener('preRemove', [$this, 'onPreRemove']);
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     */
    public function uninstall(EntityManager $entityManager): void
    {
        $eventDispatcher = $entityManager->getEventDispatcher();
        $eventDispatcher->removeListener('preRemove', [$this, 'onPreRemove']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'deleted_at_field' => 'deletedAt',
            'enable_soft_delete' => true,
        ];
    }

    /**
     * @param PreRemoveEvent $event
     * @return void
     */
    public function onPreRemove(PreRemoveEvent $event): void
    {
        $entity = $event->getEntity();
        
        if (!$this->getConfig('enable_soft_delete', true)) {
            return;
        }

        if (!$this->supportsSoftDelete($entity)) {
            return;
        }

        // Cancel the actual removal
        $event->stopPropagation();

        // Set the deleted_at timestamp instead
        $deletedAtField = $this->getConfig('deleted_at_field', 'deletedAt');
        $setter = 'set' . ucfirst($deletedAtField);
        
        if (method_exists($entity, $setter)) {
            $entity->$setter(new \DateTime());
        }
    }

    /**
     * @param object $entity
     * @return bool
     */
    private function supportsSoftDelete(object $entity): bool
    {
        $deletedAtField = $this->getConfig('deleted_at_field', 'deletedAt');
        $setter = 'set' . ucfirst($deletedAtField);
        
        return method_exists($entity, $setter);
    }
}
```

## Gestion des plugins

### Configuration des plugins

```php
<?php

declare(strict_types=1);

namespace App\Configuration;

use MulerTech\Database\Plugin\PluginManager;
use App\Plugin\AuditLogPlugin;
use App\Plugin\SmartCachePlugin;
use MulerTech\Database\Plugin\Built\ValidationPlugin;
use MulerTech\Database\Plugin\Built\SoftDeletePlugin;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PluginConfiguration
{
    /**
     * @param PluginManager $pluginManager
     * @return void
     */
    public static function configurePlugins(PluginManager $pluginManager): void
    {
        // Plugin d'audit logging
        $auditPlugin = new AuditLogPlugin(
            app('logger'),
            [
                'log_create' => true,
                'log_update' => true,
                'log_delete' => true,
                'include_data' => false,
                'excluded_entities' => [
                    'App\\Entity\\LogEntry',
                    'App\\Entity\\Cache',
                ],
            ]
        );
        $pluginManager->registerPlugin($auditPlugin);

        // Plugin de cache intelligent
        $cachePlugin = new SmartCachePlugin(
            app('cache'),
            [
                'ttl' => 7200,
                'enabled_entities' => [
                    'App\\Entity\\User',
                    'App\\Entity\\Category',
                    'App\\Entity\\Settings',
                ],
                'cache_queries' => true,
                'statistics' => true,
            ]
        );
        $pluginManager->registerPlugin($cachePlugin);

        // Plugin de validation
        $validationPlugin = new ValidationPlugin(
            app('validator'),
            [
                'validate_on_persist' => true,
                'validate_on_update' => true,
                'validation_groups' => ['Default', 'Database'],
            ]
        );
        $pluginManager->registerPlugin($validationPlugin);

        // Plugin de soft delete
        $softDeletePlugin = new SoftDeletePlugin([
            'deleted_at_field' => 'deletedAt',
            'enable_soft_delete' => true,
        ]);
        $pluginManager->registerPlugin($softDeletePlugin);
    }

    /**
     * @param PluginManager $pluginManager
     * @param string $environment
     * @return void
     */
    public static function loadEnvironmentPlugins(PluginManager $pluginManager, string $environment): void
    {
        switch ($environment) {
            case 'development':
                self::loadDevelopmentPlugins($pluginManager);
                break;
            case 'testing':
                self::loadTestingPlugins($pluginManager);
                break;
            case 'production':
                self::loadProductionPlugins($pluginManager);
                break;
        }
    }

    /**
     * @param PluginManager $pluginManager
     * @return void
     */
    private static function loadDevelopmentPlugins(PluginManager $pluginManager): void
    {
        // Load all plugins for development
        $pluginManager->loadAllPlugins();
    }

    /**
     * @param PluginManager $pluginManager
     * @return void
     */
    private static function loadTestingPlugins(PluginManager $pluginManager): void
    {
        // Disable audit logging in tests
        $auditPlugin = $pluginManager->getPlugin('audit_log');
        if ($auditPlugin) {
            $auditPlugin->setEnabled(false);
        }

        // Load remaining plugins
        $pluginManager->loadAllPlugins();
    }

    /**
     * @param PluginManager $pluginManager
     * @return void
     */
    private static function loadProductionPlugins(PluginManager $pluginManager): void
    {
        // Load performance-critical plugins only
        $pluginManager->loadPlugin('smart_cache');
        $pluginManager->loadPlugin('validation');
        $pluginManager->loadPlugin('soft_delete');
        $pluginManager->loadPlugin('audit_log');
    }
}
```

## Configuration avancée

### Plugin découvrable automatiquement

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Plugin;

use MulerTech\Database\EntityManager;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PluginDiscovery
{
    /** @var array<string> */
    private array $pluginDirectories = [];

    /**
     * @param array<string> $directories
     */
    public function __construct(array $directories = [])
    {
        $this->pluginDirectories = $directories;
    }

    /**
     * @param string $directory
     * @return void
     */
    public function addDirectory(string $directory): void
    {
        $this->pluginDirectories[] = $directory;
    }

    /**
     * @param PluginManager $pluginManager
     * @return void
     */
    public function discoverAndRegister(PluginManager $pluginManager): void
    {
        foreach ($this->pluginDirectories as $directory) {
            $this->scanDirectory($directory, $pluginManager);
        }
    }

    /**
     * @param string $directory
     * @param PluginManager $pluginManager
     * @return void
     */
    private function scanDirectory(string $directory, PluginManager $pluginManager): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->loadPluginFromFile($file->getPathname(), $pluginManager);
            }
        }
    }

    /**
     * @param string $filePath
     * @param PluginManager $pluginManager
     * @return void
     */
    private function loadPluginFromFile(string $filePath, PluginManager $pluginManager): void
    {
        $classes = $this->getClassesFromFile($filePath);

        foreach ($classes as $className) {
            if (is_subclass_of($className, PluginInterface::class)) {
                try {
                    $plugin = new $className();
                    $pluginManager->registerPlugin($plugin);
                } catch (\Throwable $e) {
                    // Log error but continue discovery
                    error_log("Failed to load plugin {$className}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * @param string $filePath
     * @return array<string>
     */
    private function getClassesFromFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $tokens = token_get_all($content);
        
        $classes = [];
        $namespace = '';
        
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j] === ';') {
                        break;
                    }
                    if (in_array($tokens[$j][0], [T_STRING, T_NS_SEPARATOR])) {
                        $namespace .= $tokens[$j][1];
                    }
                }
            }
            
            if ($tokens[$i][0] === T_CLASS) {
                $className = '';
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $className = $tokens[$j][1];
                        break;
                    }
                }
                
                if ($className) {
                    $classes[] = $namespace ? $namespace . '\\' . $className : $className;
                }
            }
        }
        
        return $classes;
    }
}
```

## Exemples de plugins

### Utilisation complète

```php
<?php

declare(strict_types=1);

namespace App\Bootstrap;

use MulerTech\Database\EntityManager;
use MulerTech\Database\Plugin\PluginManager;
use MulerTech\Database\Plugin\PluginDiscovery;
use App\Configuration\PluginConfiguration;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DatabaseSetup
{
    /**
     * @param EntityManager $entityManager
     * @param string $environment
     * @return void
     */
    public static function setupPlugins(EntityManager $entityManager, string $environment = 'production'): void
    {
        $pluginManager = new PluginManager($entityManager);
        
        // Configuration des plugins
        PluginConfiguration::configurePlugins($pluginManager);
        
        // Découverte automatique des plugins
        $discovery = new PluginDiscovery([
            __DIR__ . '/../Plugin',
            __DIR__ . '/../../vendor/mulertech/database-plugins/src',
        ]);
        $discovery->discoverAndRegister($pluginManager);
        
        // Chargement selon l'environnement
        PluginConfiguration::loadEnvironmentPlugins($pluginManager, $environment);
        
        // Attacher le gestionnaire de plugins à l'EntityManager
        $entityManager->setPluginManager($pluginManager);
    }

    /**
     * @param EntityManager $entityManager
     * @return array<string, mixed>
     */
    public static function getPluginStatus(EntityManager $entityManager): array
    {
        $pluginManager = $entityManager->getPluginManager();
        
        $status = [
            'registered' => [],
            'loaded' => [],
            'statistics' => [],
        ];
        
        foreach ($pluginManager->getRegisteredPlugins() as $name => $plugin) {
            $status['registered'][$name] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
                'enabled' => $plugin->isEnabled(),
                'dependencies' => $plugin->getDependencies(),
            ];
        }
        
        $status['loaded'] = $pluginManager->getLoadedPlugins();
        
        // Statistiques des plugins avec cette fonctionnalité
        foreach ($pluginManager->getRegisteredPlugins() as $name => $plugin) {
            if (method_exists($plugin, 'getStatistics')) {
                $status['statistics'][$name] = $plugin->getStatistics();
            }
        }
        
        return $status;
    }
}
```

---

**Voir aussi :**
- [Étendre l'ORM](extending-orm.md)
- [Types personnalisés](custom-types.md)
- [Architecture interne](internals.md)
