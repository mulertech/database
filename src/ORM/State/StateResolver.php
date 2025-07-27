<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\IdentityMap;

/**
 * Resolves entity states based on different sources
 */
final class StateResolver
{
    public function __construct(
        private readonly IdentityMap $identityMap,
        private readonly ?ChangeSetManager $changeSetManager = null
    ) {
    }

    public function resolveEntityState(object $entity): EntityState
    {
        $state = $this->identityMap->getEntityState($entity);

        if ($state === null) {
            return $this->resolveFromChangeSetManager($entity);
        }

        return $state;
    }

    private function resolveFromChangeSetManager(object $entity): EntityState
    {
        if ($this->changeSetManager === null) {
            return EntityState::DETACHED;
        }

        $scheduled = $this->changeSetManager->getScheduledInsertions();
        if (in_array($entity, $scheduled, true)) {
            return EntityState::NEW;
        }

        $scheduled = $this->changeSetManager->getScheduledDeletions();
        if (in_array($entity, $scheduled, true)) {
            return EntityState::REMOVED;
        }

        return EntityState::DETACHED;
    }
}
