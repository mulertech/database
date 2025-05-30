<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PostUpdateEvent extends AbstractEntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(Object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::postUpdate);
    }
}
