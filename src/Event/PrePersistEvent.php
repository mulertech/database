<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * Class PrePersistEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PrePersistEvent extends EntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(Object $entity, EntityManagerInterface $entityManager)
    {
        $this->setName(DbEvents::prePersist->value);
        parent::__construct($entity, $entityManager);
    }
}
