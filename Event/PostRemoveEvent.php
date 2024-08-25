<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * Class PostRemoveEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PostRemoveEvent extends EntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(Object $entity, EntityManagerInterface $entityManager) {
        $this->setName(DbEvents::postRemove);
        parent::__construct($entity, $entityManager);
    }
}