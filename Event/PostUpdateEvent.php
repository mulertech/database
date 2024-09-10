<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * Class PostUpdateEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PostUpdateEvent extends EntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(Object $entity, EntityManagerInterface $entityManager) {
        $this->setName(DbEvents::postUpdate->value);
        parent::__construct($entity, $entityManager);
    }
}