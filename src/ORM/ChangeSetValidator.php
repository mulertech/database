<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\ORM\EntityState;
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
}
