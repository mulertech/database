<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use RuntimeException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class DependencyManager
{
    /**
     * @var array<int, array<int, object>> Insertion dependencies
     */
    private array $insertionDependencies = [];

    /**
     * @var array<int, array<int, object>> Update dependencies
     */
    private array $updateDependencies = [];

    /**
     * @var array<int, array<int, object>> Deletion dependencies
     */
    private array $deletionDependencies = [];

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function addInsertionDependency(object $dependent, object $dependency): void
    {
        $dependentId = spl_object_id($dependent);
        $dependencyId = spl_object_id($dependency);

        if (!isset($this->insertionDependencies[$dependentId][$dependencyId])) {
            $this->insertionDependencies[$dependentId][$dependencyId] = $dependency;
        }
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function addUpdateDependency(object $dependent, object $dependency): void
    {
        $dependentId = spl_object_id($dependent);
        $dependencyId = spl_object_id($dependency);

        if (!isset($this->updateDependencies[$dependentId][$dependencyId])) {
            $this->updateDependencies[$dependentId][$dependencyId] = $dependency;
        }
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function addDeletionDependency(object $dependent, object $dependency): void
    {
        $dependentId = spl_object_id($dependent);
        $dependencyId = spl_object_id($dependency);

        if (!isset($this->deletionDependencies[$dependentId][$dependencyId])) {
            $this->deletionDependencies[$dependentId][$dependencyId] = $dependency;
        }
    }

    /**
     * @param object $entity
     * @return array<int, object>
     */
    public function getInsertionDependencies(object $entity): array
    {
        $oid = spl_object_id($entity);
        return $this->insertionDependencies[$oid] ?? [];
    }

    /**
     * @param object $entity
     * @return array<int, object>
     */
    public function getUpdateDependencies(object $entity): array
    {
        $oid = spl_object_id($entity);
        return $this->updateDependencies[$oid] ?? [];
    }

    /**
     * @param object $entity
     * @return array<int, object>
     */
    public function getDeletionDependencies(object $entity): array
    {
        $oid = spl_object_id($entity);
        return $this->deletionDependencies[$oid] ?? [];
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function removeInsertionDependency(object $dependent, object $dependency): void
    {
        $dependentId = spl_object_id($dependent);
        $dependencyId = spl_object_id($dependency);

        unset($this->insertionDependencies[$dependentId][$dependencyId]);

        if (empty($this->insertionDependencies[$dependentId])) {
            unset($this->insertionDependencies[$dependentId]);
        }
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function removeUpdateDependency(object $dependent, object $dependency): void
    {
        $dependentId = spl_object_id($dependent);
        $dependencyId = spl_object_id($dependency);

        unset($this->updateDependencies[$dependentId][$dependencyId]);

        if (empty($this->updateDependencies[$dependentId])) {
            unset($this->updateDependencies[$dependentId]);
        }
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function removeDeletionDependency(object $dependent, object $dependency): void
    {
        $dependentId = spl_object_id($dependent);
        $dependencyId = spl_object_id($dependency);

        unset($this->deletionDependencies[$dependentId][$dependencyId]);

        if (empty($this->deletionDependencies[$dependentId])) {
            unset($this->deletionDependencies[$dependentId]);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function removeDependencies(object $entity): void
    {
        $oid = spl_object_id($entity);
        unset($this->insertionDependencies[$oid], $this->updateDependencies[$oid], $this->deletionDependencies[$oid]);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function clearDependencies(object $entity): void
    {
        $this->removeDependencies($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function hasDependencies(object $entity): bool
    {
        $oid = spl_object_id($entity);
        return !empty($this->insertionDependencies[$oid]) ||
               !empty($this->updateDependencies[$oid]) ||
               !empty($this->deletionDependencies[$oid]);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->insertionDependencies = [];
        $this->updateDependencies = [];
        $this->deletionDependencies = [];
    }

    /**
     * @param array<int, object> $entities
     * @return array<int, object>
     * @throws RuntimeException
     */
    public function orderByDependencies(array $entities): array
    {
        if (empty($this->insertionDependencies)) {
            return $entities;
        }

        $ordered = [];
        $visited = [];
        $visiting = [];

        foreach ($entities as $oid => $entity) {
            if (!isset($visited[$oid])) {
                $this->visitEntity($oid, $entities, $visited, $visiting, $ordered);
            }
        }

        return $ordered;
    }

    /**
     * @param int $oid
     * @param array<int, object> $entities
     * @param array<int, bool> $visited
     * @param array<int, bool> $visiting
     * @param array<int, object> $ordered
     * @throws RuntimeException
     */
    private function visitEntity(
        int $oid,
        array $entities,
        array &$visited,
        array &$visiting,
        array &$ordered
    ): void {
        if (!isset($entities[$oid])) {
            return;
        }

        if (isset($visiting[$oid])) {
            throw new RuntimeException('Circular dependency detected');
        }

        if (isset($visited[$oid])) {
            return;
        }

        $visiting[$oid] = true;

        $this->visitEntityDependencies($oid, $entities, $visited, $visiting, $ordered);

        unset($visiting[$oid]);
        $visited[$oid] = true;
        $ordered[$oid] = $entities[$oid];
    }

    /**
     * @param int $oid
     * @param array<int, object> $entities
     * @param array<int, bool> $visited
     * @param array<int, bool> $visiting
     * @param array<int, object> $ordered
     * @throws RuntimeException
     */
    private function visitEntityDependencies(
        int $oid,
        array $entities,
        array &$visited,
        array &$visiting,
        array &$ordered
    ): void {
        $dependencies = $this->insertionDependencies[$oid] ?? [];

        foreach ($dependencies as $dependencyId => $dependency) {
            $this->visitEntity($dependencyId, $entities, $visited, $visiting, $ordered);
        }
    }
}
