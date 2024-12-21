<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManager;

/**
 * Class PrePersistEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PrePersistEvent extends EntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManager $entityManager
     */
    public function __construct(Object $entity, EntityManager $entityManager) {
        $this->setName(DbEvents::prePersist->value);
        parent::__construct($entity, $entityManager);
    }
}