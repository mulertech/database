<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * @author Sébastien Muler
 */
class PreUpdateEvent extends AbstractEntityEvent
{
    public function __construct(
        object $entity,
        EntityManagerInterface $entityManager,
        private readonly ?ChangeSet $entityChanges,
    ) {
        parent::__construct($entity, $entityManager, DbEvents::preUpdate);
    }

    public function getEntityChanges(): ?ChangeSet
    {
        return $this->entityChanges;
    }
}
