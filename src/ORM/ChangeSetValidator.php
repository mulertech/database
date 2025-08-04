<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\ORM\Scheduler\EntityScheduler;

/**
 * Class ChangeSetValidator
 *
 * Handles validation logic for ChangeSet operations
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class ChangeSetValidator
{
    public function __construct(
        private IdentityMap $identityMap
    ) {
    }

    /**
     * @param int|string|null $entityId
     * @param EntityState|null $metadata
     * @return bool
     */
    public function shouldSkipInsertion(int|string|null $entityId, ?EntityState $metadata): bool
    {
        if ($entityId !== null && $metadata !== null && $metadata->isManaged()) {
            return true;
        }

        return $entityId !== null;
    }

    /**
     * @param object $entity
     * @param EntityScheduler $scheduler
     * @return bool
     */
    public function canScheduleUpdate(object $entity, EntityScheduler $scheduler): bool
    {
        return $this->identityMap->isManaged($entity) &&
               !$scheduler->isScheduledForInsertion($entity) &&
               !$scheduler->isScheduledForDeletion($entity) &&
               !$scheduler->isScheduledForUpdate($entity);
    }

    /**
     * @param object $entity
     * @param EntityScheduler $scheduler
     * @return bool
     */
    public function canScheduleDeletion(object $entity, EntityScheduler $scheduler): bool
    {
        return $this->identityMap->isManaged($entity) &&
               !$scheduler->isScheduledForInsertion($entity) &&
               !$scheduler->isScheduledForDeletion($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isValidForPersistence(object $entity): bool
    {
        if ($this->identityMap->isManaged($entity)) {
            return false;
        }

        return true;
    }

    /**
     * @param ChangeSet $changeSet
     * @return bool
     */
    public function validateChangeSet(ChangeSet $changeSet): bool
    {
        return !empty($changeSet->getChanges());
    }
}
