<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

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
     * @param array<string, array<int, string>> $entityChanges
     */
    public function __construct(
        Object $entity,
        EntityManagerInterface $entityManager,
        private readonly array $entityChanges
    ) {
        parent::__construct($entity, $entityManager, DbEvents::preUpdate);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getEntityChanges(): array
    {
        return $this->entityChanges;
    }
}
