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
     * @var array $entityChanges
     */
    private array $entityChanges;

    /**
     * @param Object $entity
     * @param EntityManager $entityManager
     * @param array $entityChanges
     */
    public function __construct(Object $entity, EntityManager $entityManager, array $entityChanges) {
        $this->setName(DbEvents::preUpdate);
        $this->entityChanges = $entityChanges;
        parent::__construct($entity, $entityManager);
    }

    /**
     * @return array
     */
    public function getEntityChanges(): array
    {
        return $this->entityChanges;
    }
}