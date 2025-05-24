<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\EventManager\Event;

/**
 * Class EntityEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class EntityEvent extends Event
{
    /**
     * @var Object $entity
     */
    private Object $entity;
    /**
     * @var EntityManagerInterface $entityManager
     */
    private EntityManagerInterface $entityManager;

    /**
     * @param Object $entity
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(Object $entity, EntityManagerInterface $entityManager)
    {
        $this->entity = $entity;
        $this->entityManager = $entityManager;
    }

    /**
     * @return Object
     */
    public function getEntity(): Object
    {
        return $this->entity;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
