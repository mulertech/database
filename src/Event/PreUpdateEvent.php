<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * Class PreUpdateEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PreUpdateEvent extends EntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $entityManager
     * @param array<int, array<string, array<int, string>>> $entityChanges
     */
    public function __construct(
        Object $entity,
        EntityManagerInterface $entityManager,
        private readonly array $entityChanges
    ) {
        $this->setName(DbEvents::preUpdate->value);
        parent::__construct($entity, $entityManager);
    }

    /**
     * @return array<int, array<string, array<int, string>>>
     */
    public function getEntityChanges(): array
    {
        return $this->entityChanges;
    }
}