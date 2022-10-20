<?php

namespace mtphp\Database\Event;

use mtphp\Database\ORM\EntityManager;
use mtphp\Entity\Entity;
use mtphp\EventManager\Event;

/**
 * Class PostPersistEvent
 * @package mtphp\Database\Event
 * @author Sébastien Muler
 */
class PostPersistEvent extends Event
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
        $this->setName(DbEvents::postPersist);
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