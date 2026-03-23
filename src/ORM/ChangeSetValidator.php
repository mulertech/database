<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\ORM\Scheduler\EntityScheduler;

/**
 * Class ChangeSetValidator.
 *
 * Handles validation logic for ChangeSet operations
 *
 * @author Sébastien Muler
 */
final readonly class ChangeSetValidator
{
    public function __construct(
        private IdentityMap $identityMap,
    ) {
    }

    public function shouldSkipInsertion(int|string|null $entityId, ?EntityState $metadata): bool
    {
        if (null !== $entityId && null !== $metadata && $metadata->isManaged()) {
            return true;
        }

        return null !== $entityId;
    }

    public function canScheduleUpdate(object $entity, EntityScheduler $scheduler): bool
    {
        return $this->identityMap->isManaged($entity)
               && !$scheduler->isScheduledForInsertion($entity)
               && !$scheduler->isScheduledForDeletion($entity)
               && !$scheduler->isScheduledForUpdate($entity);
    }

    public function canScheduleDeletion(object $entity, EntityScheduler $scheduler): bool
    {
        return $this->identityMap->isManaged($entity)
               && !$scheduler->isScheduledForInsertion($entity)
               && !$scheduler->isScheduledForDeletion($entity);
    }

    public function validateChangeSet(ChangeSet $changeSet): bool
    {
        return !empty($changeSet->getChanges());
    }
}
