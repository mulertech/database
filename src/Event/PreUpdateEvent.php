<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PreUpdateEvent extends AbstractEntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $entityManager
     * @param ChangeSet|null $entityChanges
     */
    public function __construct(
        Object $entity,
        EntityManagerInterface $entityManager,
        private readonly ?ChangeSet $entityChanges
    ) {
        parent::__construct($entity, $entityManager, DbEvents::preUpdate);
    }

    /**
     * @return ChangeSet|null
     */
    public function getEntityChanges(): ?ChangeSet
    {
        return $this->entityChanges;
    }
}
