<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use SplObjectStorage;
use WeakReference;

/**
 * Registre global des entités avec métadonnées et statistiques
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
final class EntityRegistry
{
    /** @var SplObjectStorage<object, array{registeredAt: DateTimeImmutable, lastAccessed: DateTimeImmutable, accessCount: int}> */
    private SplObjectStorage $registry;

    /** @var array<class-string, int> */
    private array $entityCountByClass = [];

    /** @var array<class-string, array<string, int>> */
    private array $entityStats = [];

    /** @var int */
    private int $totalEntitiesRegistered = 0;

    /** @var int */
    private int $totalEntitiesUnregistered = 0;

    /** @var bool */
    private bool $enableGarbageCollection = true;

    /** @var int */
    private int $gcInterval = 100; // Run GC every 100 operations

    /** @var int */
    private int $operationCount = 0;

    public function __construct()
    {
        $this->registry = new SplObjectStorage();
    }

    /**
     * @param object $entity
     * @return void
     */
    public function register(object $entity): void
    {
        $entityClass = $entity::class;
        $now = new DateTimeImmutable();

        if ($this->registry->contains($entity)) {
            // Update access time
            $data = $this->registry[$entity];
            $data['lastAccessed'] = $now;
            $data['accessCount']++;
            $this->registry[$entity] = $data;
        } else {
            // New registration
            $this->registry[$entity] = [
                'registeredAt' => $now,
                'lastAccessed' => $now,
                'accessCount' => 1
            ];

            // Update counters
            $this->entityCountByClass[$entityClass] = ($this->entityCountByClass[$entityClass] ?? 0) + 1;
            $this->totalEntitiesRegistered++;
        }

        // Update stats
        $this->updateStats($entityClass, 'registered');

        // Periodic garbage collection
        $this->operationCount++;
        if ($this->enableGarbageCollection && $this->operationCount % $this->gcInterval === 0) {
            $this->runGarbageCollection();
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function unregister(object $entity): void
    {
        if (!$this->registry->contains($entity)) {
            return;
        }

        $entityClass = $entity::class;

        // Remove from registry
        unset($this->registry[$entity]);

        // Update counters
        if (isset($this->entityCountByClass[$entityClass])) {
            $this->entityCountByClass[$entityClass]--;
            if ($this->entityCountByClass[$entityClass] <= 0) {
                unset($this->entityCountByClass[$entityClass]);
            }
        }

        $this->totalEntitiesUnregistered++;

        // Update stats
        $this->updateStats($entityClass, 'unregistered');
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isRegistered(object $entity): bool
    {
        return $this->registry->contains($entity);
    }

    /**
     * @param object $entity
     * @return array{registeredAt: DateTimeImmutable, lastAccessed: DateTimeImmutable, accessCount: int}|null
     */
    public function getMetadata(object $entity): ?array
    {
        if (!$this->registry->contains($entity)) {
            return null;
        }

        return $this->registry[$entity];
    }

    /**
     * @param class-string $entityClass
     * @return int
     */
    public function getCountByClass(string $entityClass): int
    {
        return $this->entityCountByClass[$entityClass] ?? 0;
    }

    /**
     * @return array<class-string, int>
     */
    public function getCountsByClass(): array
    {
        return $this->entityCountByClass;
    }

    /**
     * @return array<object>
     */
    public function getAllRegisteredEntities(): array
    {
        $entities = [];
        foreach ($this->registry as $entity) {
            $entities[] = $entity;
        }
        return $entities;
    }

    /**
     * @param class-string $entityClass
     * @return array<object>
     */
    public function getEntitiesByClass(string $entityClass): array
    {
        $entities = [];
        foreach ($this->registry as $entity) {
            if ($entity::class === $entityClass) {
                $entities[] = $entity;
            }
        }
        return $entities;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->registry = new SplObjectStorage();
        $this->entityCountByClass = [];
        $this->entityStats = [];
        $this->operationCount = 0;
    }

    /**
     * @return void
     */
    public function updateStatistics(): void
    {
        foreach ($this->entityCountByClass as $class => $count) {
            $this->entityStats[$class]['currentCount'] = $count;
            $this->entityStats[$class]['lastUpdated'] = time();
        }
    }

    /**
     * @return array{
     *     totalRegistered: int,
     *     totalUnregistered: int,
     *     currentlyRegistered: int,
     *     classCounts: array<class-string, int>,
     *     memoryUsage: int,
     *     gcEnabled: bool,
     *     gcInterval: int,
     *     operationCount: int
     * }
     */
    public function getStatistics(): array
    {
        return [
            'totalRegistered' => $this->totalEntitiesRegistered,
            'totalUnregistered' => $this->totalEntitiesUnregistered,
            'currentlyRegistered' => count($this->registry),
            'classCounts' => $this->entityCountByClass,
            'memoryUsage' => memory_get_usage(true),
            'gcEnabled' => $this->enableGarbageCollection,
            'gcInterval' => $this->gcInterval,
            'operationCount' => $this->operationCount,
        ];
    }

    /**
     * @param bool $enable
     * @return void
     */
    public function setGarbageCollection(bool $enable): void
    {
        $this->enableGarbageCollection = $enable;
    }

    /**
     * @param int $interval
     * @return void
     */
    public function setGarbageCollectionInterval(int $interval): void
    {
        $this->gcInterval = max(1, $interval);
    }

    /**
     * @return void
     */
    private function runGarbageCollection(): void
    {
        gc_collect_cycles();

        foreach ($this->registry as $entity) {
            $this->unregister($entity);
        }
    }

    /**
     * @param class-string $entityClass
     * @param string $operation
     * @return void
     */
    private function updateStats(string $entityClass, string $operation): void
    {
        if (!isset($this->entityStats[$entityClass])) {
            $this->entityStats[$entityClass] = [
                'registered' => 0,
                'unregistered' => 0,
                'currentCount' => 0,
                'lastUpdated' => time()
            ];
        }

        $this->entityStats[$entityClass][$operation] = ($this->entityStats[$entityClass][$operation] ?? 0) + 1;
        $this->entityStats[$entityClass]['currentCount'] = $this->entityCountByClass[$entityClass] ?? 0;
        $this->entityStats[$entityClass]['lastUpdated'] = time();
    }
}
