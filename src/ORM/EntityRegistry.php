<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use WeakMap;

/**
 * Class EntityRegistry
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class EntityRegistry
{
    /** @var WeakMap<object, array{
     *     registeredAt: DateTimeImmutable,
     *     lastAccessed: DateTimeImmutable,
     *     accessCount: int
     * }>
     */
    private WeakMap $registry;

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
        $this->registry = new WeakMap();
    }

    /**
     * @param object $entity
     * @return void
     */
    public function register(object $entity): void
    {
        $entityClass = $entity::class;
        $now = new DateTimeImmutable();

        if (isset($this->registry[$entity])) {
            // Update access time
            $data = $this->registry[$entity];
            $data['lastAccessed'] = $now;
            $data['accessCount']++;
            $this->registry[$entity] = $data;
            return;
        }

        // New registration
        $this->registry[$entity] = [
            'registeredAt' => $now,
            'lastAccessed' => $now,
            'accessCount' => 1,
        ];

        // Update counters
        $this->entityCountByClass[$entityClass] = ($this->entityCountByClass[$entityClass] ?? 0) + 1;
        $this->totalEntitiesRegistered++;

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
        if (!isset($this->registry[$entity])) {
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
     * @return void
     */
    public function clear(): void
    {
        $this->registry = new WeakMap();
        $this->entityCountByClass = [];
        $this->entityStats = [];
        $this->operationCount = 0;
    }

    /**
     * @return void
     */
    private function runGarbageCollection(): void
    {
        gc_collect_cycles();
        // WeakMap automatically removes garbage collected entities
        // No manual cleanup needed
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
                'lastUpdated' => time(),
            ];
        }

        $this->entityStats[$entityClass][$operation] = ($this->entityStats[$entityClass][$operation] ?? 0) + 1;
        $this->entityStats[$entityClass]['currentCount'] = $this->entityCountByClass[$entityClass] ?? 0;
        $this->entityStats[$entityClass]['lastUpdated'] = time();
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isRegistered(object $entity): bool
    {
        return isset($this->registry[$entity]);
    }

    /**
     * @return array<object>
     */
    public function getRegisteredEntities(): array
    {
        $entities = [];
        foreach ($this->registry as $entity => $data) {
            $entities[] = $entity;
        }
        return $entities;
    }

    /**
     * @param class-string $className
     * @return array<object>
     */
    public function getRegisteredEntitiesByClass(string $className): array
    {
        $entities = [];
        foreach ($this->registry as $entity => $data) {
            if ($entity::class === $className) {
                $entities[] = $entity;
            }
        }
        return $entities;
    }
}
