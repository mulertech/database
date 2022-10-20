<?php

namespace mtphp\Database\Event;

use mtphp\Database\ORM\EntityManagerInterface;
use mtphp\Entity\Entity;
use mtphp\EventManager\Event;

/**
 * Class PostUpdateEvent
 * @package mtphp\Database\Event
 * @author Sébastien Muler
 */
class PostUpdateEvent extends Event
{

    /**
     * @var Entity
     */
    private $entity;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @param Entity $entity
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(Entity $entity, EntityManagerInterface $entityManager) {
        $this->setName(DbEvents::postUpdate);
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
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

}