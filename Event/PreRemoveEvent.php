<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Entity\Entity;
use MulerTech\EventManager\Event;

/**
 * Class PreRemoveEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PreRemoveEvent extends Event
{

    /**
     * @var Entity
     */
    private $entity;
    /**
     * @var EntityManagerInterface $entityManager
     */
    private $entityManager;

    /**
     * @param Entity $entity
     * @param EntityManagerInterface $em
     */
    public function __construct(Entity $entity, EntityManagerInterface $em) {
        $this->setName(DbEvents::preRemove);
        $this->entity = $entity;
        $this->entityManager = $em;
    }

    /**
     * @return Entity
     */
    public function getEntity(): Entity
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