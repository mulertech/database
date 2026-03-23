<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;

/**
 * Class RelationManager.
 *
 * @author Sébastien Muler
 */
class RelationManager
{
    private EntityRelationLoader $relationLoader;

    /**
     * @var array<int> Track processed entities during the entire flush cycle
     */
    private array $processedEntities = [];

    private ManyToManyProcessor $manyToManyProcessor;

    private LinkEntityManager $linkEntityManager;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateManagerInterface $stateManager,
    ) {
        $this->relationLoader = new EntityRelationLoader($this->entityManager);
        $this->manyToManyProcessor = new ManyToManyProcessor($this->entityManager);
        $this->linkEntityManager = new LinkEntityManager($this->entityManager, $this->stateManager);
    }

    /**
     * @param array<string, mixed> $entityData
     *
     * @throws \ReflectionException
     */
    public function loadEntityRelations(object $entity, array $entityData): void
    {
        $this->relationLoader->loadRelations($entity, $entityData);
    }

    public function startFlushCycle(): void
    {
        $this->processedEntities = [];
        $this->manyToManyProcessor->startFlushCycle();
    }

    /**
     * @throws \ReflectionException
     */
    public function processRelationChanges(): void
    {
        $entitiesToProcess = $this->collectEntitiesToProcess();

        foreach ($entitiesToProcess as $entity) {
            $this->processEntityRelations($entity);
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function flush(): void
    {
        $operations = $this->manyToManyProcessor->getOperations();

        foreach ($operations as $operation) {
            $this->linkEntityManager->processOperation($operation);
        }
    }

    public function clear(): void
    {
        $this->processedEntities = [];
        $this->manyToManyProcessor->clear();
        $this->linkEntityManager->clear();
    }

    /**
     * Collect all entities to process to avoid duplicates.
     *
     * @return array<int, object>
     */
    private function collectEntitiesToProcess(): array
    {
        $entitiesToProcess = [];

        // Add scheduled insertions
        $scheduledInsertions = $this->stateManager->getScheduledInsertions();
        foreach ($scheduledInsertions as $entity) {
            $entityId = spl_object_id($entity);
            $entitiesToProcess[$entityId] = $entity;
        }

        // Add managed entities that are not scheduled for deletion
        $managedEntities = $this->stateManager->getManagedEntities();
        foreach ($managedEntities as $entity) {
            if (!$this->stateManager->isScheduledForDeletion($entity)) {
                $entityId = spl_object_id($entity);
                if (!isset($entitiesToProcess[$entityId])) {
                    $entitiesToProcess[$entityId] = $entity;
                }
            }
        }

        return $entitiesToProcess;
    }

    /**
     * @throws \ReflectionException
     */
    private function processEntityRelations(object $entity): void
    {
        $entityId = spl_object_id($entity);

        if (in_array($entityId, $this->processedEntities, true)) {
            return;
        }

        $this->processedEntities[] = $entityId;

        $this->manyToManyProcessor->process($entity);
    }
}
