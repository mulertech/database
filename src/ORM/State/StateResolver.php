<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\IdentityMap;

/**
 * Resolves entity states based on different sources
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class StateResolver
{
    public function __construct(
        private IdentityMap $identityMap,
        private ?ChangeSetManager $changeSetManager = null
    ) {
    }

    /**
     * @param object $entity
     * @return EntityLifecycleState
     */
    public function resolveEntityLifecycleState(object $entity): EntityLifecycleState
    {
        $state = $this->identityMap->getEntityState($entity);

        if ($state === null) {
            return $this->resolveFromChangeSetManager($entity);
        }

        return $state;
    }

    /**
     * @param object $entity
     * @return EntityLifecycleState
     */
    private function resolveFromChangeSetManager(object $entity): EntityLifecycleState
    {
        if ($this->changeSetManager === null) {
            return EntityLifecycleState::DETACHED;
        }

        $scheduled = $this->changeSetManager->getScheduledInsertions();
        if (in_array($entity, $scheduled, true)) {
            return EntityLifecycleState::NEW;
        }

        $scheduled = $this->changeSetManager->getScheduledDeletions();
        if (in_array($entity, $scheduled, true)) {
            return EntityLifecycleState::REMOVED;
        }

        return EntityLifecycleState::DETACHED;
    }
}
