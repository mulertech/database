<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * Class PostPersistEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PostPersistEvent extends EntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(Object $entity, EntityManagerInterface $entityManager)
    {
        $this->setName(DbEvents::postPersist->value);
        parent::__construct($entity, $entityManager);
    }
}
