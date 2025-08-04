<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\ORM\State\EntityLifecycleState;
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

        if ($entityId !== null && $metadata === null) {
            $currentData = $this->changeDetector->extractCurrentData($entity);
            $entityState = new EntityState(
                $entity::class,
                EntityLifecycleState::MANAGED,
                $currentData,
                new DateTimeImmutable()
            );
            $this->identityMap->add($entity, $entityId, $entityState);
            $this->registry->register($entity);
            return;
        }

        if ($this->validator->shouldSkipInsertion($entityId, $metadata)) {
            return;
        }

        $scheduler->scheduleForInsertion($entity);
        $this->registry->register($entity);

        $this->handleEntityLifecycleStateForInsertion($entity, $metadata, $stateManager);
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

        if (!$this->validator->canScheduleDeletion($entity, $scheduler)) {
            return;
        }

        $scheduler->scheduleForDeletion($entity);

        // Handle state transition
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null
            && $metadata->state !== EntityLifecycleState::REMOVED
            && $metadata->state !== EntityLifecycleState::NEW
        ) {
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
     * @param ?EntityState $metadata
     * @param EntityStateManager $stateManager
     * @return void
     */
    private function handleEntityLifecycleStateForInsertion(
        object $entity,
        ?EntityState $metadata,
        EntityStateManager $stateManager
    ): void {
        if ($metadata === null) {
            $this->identityMap->add($entity);
            return;
        }

        if ($metadata->state !== EntityLifecycleState::NEW) {
            $stateManager->transitionToNew($entity);
        }
    }
}
