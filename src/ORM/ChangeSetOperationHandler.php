<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\ORM\State\EntityState;
use MulerTech\Database\ORM\State\EntityStateManager;

/**
 * Class ChangeSetOperationHandler
 *
 * Handles complex operations for ChangeSetManager
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class ChangeSetOperationHandler
{
    public function __construct(
        private IdentityMap $identityMap,
        private EntityRegistry $registry,
        private ChangeDetector $changeDetector,
        private ChangeSetValidator $validator
    ) {
    }

    /**
     * @param object $entity
     * @param EntityScheduler $scheduler
     * @param EntityStateManager $stateManager
     * @param EntityProcessor $entityProcessor
     * @return void
     */
    public function handleInsertionScheduling(
        object $entity,
        EntityScheduler $scheduler,
        EntityStateManager $stateManager,
        EntityProcessor $entityProcessor
    ): void {
        if ($scheduler->isScheduledForInsertion($entity)) {
            return;
        }

        $metadata = $this->identityMap->getMetadata($entity);
        $entityId = $entityProcessor->extractEntityId($entity);

        if ($this->validator->shouldSkipInsertion($entityId, $metadata)) {
            return;
        }

        $scheduler->scheduleForInsertion($entity);
        $this->registry->register($entity);

        $this->handleEntityStateForInsertion($entity, $metadata, $stateManager);
        $scheduler->removeFromSchedule($entity, 'updates');
        $scheduler->removeFromSchedule($entity, 'deletions');
    }

    /**
     * @param object $entity
     * @param EntityScheduler $scheduler
     * @return void
     */
    public function handleUpdateScheduling(object $entity, EntityScheduler $scheduler): void
    {
        if (!$this->validator->canScheduleUpdate($entity, $scheduler)) {
            return;
        }

        $scheduler->scheduleForUpdate($entity);
        $this->registry->register($entity);
    }

    /**
     * @param object $entity
     * @param EntityScheduler $scheduler
     * @param EntityStateManager $stateManager
     * @return void
     */
    public function handleDeletionScheduling(
        object $entity,
        EntityScheduler $scheduler,
        EntityStateManager $stateManager
    ): void {
        if ($scheduler->isScheduledForDeletion($entity)) {
            return;
        }

        $scheduler->scheduleForDeletion($entity);

        // Handle state transition
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null && $metadata->state !== EntityState::REMOVED && $metadata->state !== EntityState::NEW) {
            $stateManager->transitionToRemoved($entity);
        }

        $scheduler->removeFromSchedule($entity, 'insertions');
        $scheduler->removeFromSchedule($entity, 'updates');
    }

    /**
     * @param object $entity
     * @param EntityScheduler $scheduler
     * @param EntityStateManager $stateManager
     * @return void
     */
    public function handleDetachment(
        object $entity,
        EntityScheduler $scheduler,
        EntityStateManager $stateManager
    ): void {
        $scheduler->removeFromAllSchedules($entity);
        $stateManager->transitionToDetached($entity);
        $this->registry->unregister($entity);
    }

    /**
     * @param object $entity
     * @param ?EntityMetadata $metadata
     * @param EntityStateManager $stateManager
     * @return void
     */
    private function handleEntityStateForInsertion(
        object $entity,
        ?EntityMetadata $metadata,
        EntityStateManager $stateManager
    ): void {
        if ($metadata === null) {
            $this->identityMap->add($entity);
            return;
        }

        if ($metadata->state !== EntityState::NEW) {
            $newData = $this->changeDetector->extractCurrentData($entity);
            $stateManager->tryTransitionToNew($entity, $newData);
        }
    }
}
