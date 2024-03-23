<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManager;
use MulerTech\Entity\Entity;
use MulerTech\EventManager\Event;

/**
 * Class PostRemoveEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PostRemoveEvent extends Event
{

    /**
     * @var Entity
     */
    private $entity;
    /**
     * @var EntityManager $entityManager
     */
    private $entityManager;

    /**
     * PostRemoveEvent constructor.
     * @param Entity $entity
     * @param EntityManager $entityManager
     */
    public function __construct(Entity $entity, EntityManager $entityManager) {
        $this->setName(DbEvents::postRemove);
        $this->entity = $entity;
        $this->entityManager = $entityManager;
    }

    /**
     * @return Entity
     */
    public function getEntity(): Entity
    {
        return $this->entity;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }
}