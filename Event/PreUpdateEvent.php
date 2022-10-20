<?php

namespace mtphp\Database\Event;

use mtphp\Database\ORM\EntityManager;
use mtphp\Entity\Entity;
use mtphp\EventManager\Event;

/**
 * Class PreUpdateEvent
 * @package mtphp\Database\Event
 * @author Sébastien Muler
 */
class PreUpdateEvent extends Event
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
     * @var array $entityChanges
     */
    private $entityChanges;

    /**
     * @param Entity $entity
     * @param EntityManager $entityManager
     * @param array $entityChanges
     */
    public function __construct(Entity $entity, EntityManager $entityManager, array $entityChanges) {
        $this->setName(DbEvents::preUpdate);
        $this->entity = $entity;
        $this->entityManager = $entityManager;
        $this->entityChanges = $entityChanges;
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

    /**
     * @return array
     */
    public function getEntityChanges(): array
    {
        return $this->entityChanges;
    }
}