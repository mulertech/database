<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManager;
use MulerTech\Entity\Entity;
use MulerTech\EventManager\Event;

/**
 * Class PrePersistEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PrePersistEvent extends Event
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
     * @param Entity $entity
     * @param EntityManager $entityManager
     */
    public function __construct(Entity $entity, EntityManager $entityManager) {
        $this->setName(DbEvents::prePersist);
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