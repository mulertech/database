<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManager;
use MulerTech\EventManager\Event;

/**
 * Class PostFlushEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PostFlushEvent extends Event
{

    /**
     * @var EntityManager $entityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager) {
        $this->setName(DbEvents::postFlush);
        $this->entityManager = $entityManager;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }
}