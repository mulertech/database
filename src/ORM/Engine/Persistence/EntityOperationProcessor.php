<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use DateTimeImmutable;
use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\StateManagerInterface;
use ReflectionException;

/**
 * Handles entity operations (insert, update, delete) with metadata management
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityOperationProcessor
{
    private ?EntityProcessor $entityProcessor = null;

    public function __construct(
        private readonly StateManagerInterface $stateManager,
        private readonly ChangeSetManager $changeSetManager,
        private readonly ChangeDetector $changeDetector,
        private readonly IdentityMap $identityMap,
        private readonly EventDispatcher $eventDispatcher,
        private readonly InsertionProcessor $insertionProcessor,
        private readonly UpdateProcessor $updateProcessor,
        private readonly DeletionProcessor $deletionProcessor,
    ) {
    }

    /**
     * @param object $entity
     * @param int $flushDepth
     * @return void
     * @throws ReflectionException
     */
    public function processInsertion(object $entity, int $flushDepth): void
    {
        $entityId = $this->extractEntityId($entity);
        if ($entityId !== null) {
            $this->stateManager->manage($entity);
            return;
        }

        $this->eventDispatcher->callEntityEvent($entity, 'prePersist', $flushDepth);
        $this->insertionProcessor->process($entity);
        $this->stateManager->manage($entity);
        $this->updateEntityMetadata($entity);
        $this->eventDispatcher->callEntityEvent($entity, 'postPersist', $flushDepth);
    }

    /**
     * @param object $entity
     * @param int $flushDepth
     * @return void
     * @throws ReflectionException
     */
    public function processUpdate(object $entity, int $flushDepth): void
    {
        $changeSet = $this->changeSetManager->getChangeSet($entity);

        if ($changeSet === null || $changeSet->isEmpty()) {
            return;
        }

        $this->eventDispatcher->callEntityEvent($entity, 'preUpdate', $flushDepth);
        $this->updateProcessor->process($entity, $changeSet->getChanges());
        $this->updateEntityMetadata($entity);
        $this->eventDispatcher->callEntityEvent($entity, 'postUpdate', $flushDepth);
    }

    /**
     * @param object $entity
     * @param int $flushDepth
     * @return void
     * @throws ReflectionException
     */
    public function processDeletion(object $entity, int $flushDepth): void
    {
        $this->eventDispatcher->callEntityEvent($entity, 'preRemove', $flushDepth);
        $this->deletionProcessor->process($entity);

        // Only detach if entity is not already in removed state
        $currentState = $this->stateManager->getEntityState($entity);
        if ($currentState !== EntityLifecycleState::REMOVED) {
            $this->stateManager->detach($entity);
        }

        $this->eventDispatcher->callEntityEvent($entity, 'postRemove', $flushDepth);
    }

    /**
     * Process any pending operations that were scheduled during events
     * @param int $flushDepth
     * @return void
     * @throws ReflectionException
     */
    public function processPostEventOperations(int $flushDepth): void
    {
        // Process any new insertions that might have been scheduled
        $pendingInsertions = $this->changeSetManager->getScheduledInsertions();
        foreach ($pendingInsertions as $insertEntity) {
            $this->processInsertion($insertEntity, $flushDepth);
        }

        // Process any pending updates
        $pendingUpdates = $this->changeSetManager->getScheduledUpdates();
        foreach ($pendingUpdates as $updateEntity) {
            $this->processUpdate($updateEntity, $flushDepth);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    private function updateEntityMetadata(object $entity): void
    {
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata === null) {
            return;
        }

        $currentData = $this->changeDetector->extractCurrentData($entity);
        $entityId = $this->extractEntityId($entity);
        $identifier = $entityId ?? $metadata->identifier;

        $newMetadata = new EntityState(
            $metadata->className,
            $identifier,
            EntityLifecycleState::MANAGED,
            $currentData,
            $metadata->loadedAt,
            new DateTimeImmutable()
        );
        $this->identityMap->updateMetadata($entity, $newMetadata);
    }

    /**
     * @param object $entity
     * @return string|int|null
     */
    private function extractEntityId(object $entity): string|int|null
    {
        return $this->getEntityProcessor()->extractEntityId($entity);
    }

    /**
     * @return EntityProcessor
     */
    private function getEntityProcessor(): EntityProcessor
    {
        if ($this->entityProcessor === null) {
            $this->entityProcessor = new EntityProcessor(
                $this->changeDetector,
                $this->identityMap
            );
        }
        return $this->entityProcessor;
    }
}
