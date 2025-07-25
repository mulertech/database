<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * Handles entity insertion dependencies and ordering
 */
final class DependencyManager
{
    /**
     * @var array<int, array<int>> Insertion dependencies
     */
    private array $insertionDependencies = [];

    public function addInsertionDependency(object $dependent, object $dependency): void
    {
        $dependentId = spl_object_id($dependent);
        $dependencyId = spl_object_id($dependency);

        $this->insertionDependencies[$dependentId][] = $dependencyId;
    }

    public function removeDependencies(object $entity): void
    {
        $oid = spl_object_id($entity);
        unset($this->insertionDependencies[$oid]);
    }

    public function clear(): void
    {
        $this->insertionDependencies = [];
    }

    /**
     * @param array<int, object> $entities
     * @return array<int, object>
     */
    public function orderByDependencies(array $entities): array
    {
        if (empty($this->insertionDependencies)) {
            return $entities;
        }

        $ordered = [];
        $visited = [];

        foreach ($entities as $oid => $entity) {
            if (!isset($visited[$oid])) {
                $this->visitDependencies($oid, $entities, $visited, $ordered);
            }
        }

        return $ordered;
    }

    /**
     * @param int $oid
     * @param array<int, object> $entities
     * @param array<int, bool> $visited
     * @param array<int, object> $ordered
     * @return void
     */
    private function visitDependencies(int $oid, array $entities, array &$visited, array &$ordered): void
    {
        $visited[$oid] = true;

        if (isset($this->insertionDependencies[$oid])) {
            foreach ($this->insertionDependencies[$oid] as $dependencyId) {
                if (!isset($visited[$dependencyId]) && isset($entities[$dependencyId])) {
                    $this->visitDependencies($dependencyId, $entities, $visited, $ordered);
                }
            }
        }

        if (isset($entities[$oid])) {
            $ordered[$oid] = $entities[$oid];
        }
    }
}
