<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManager;

/**
 * Class PreUpdateEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PreUpdateEvent extends EntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManager $entityManager
     * @param array<int, array<string, array<int, string>>> $entityChanges
     */
    public function __construct(Object $entity, EntityManager $entityManager, private array $entityChanges) {
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