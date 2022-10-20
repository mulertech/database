<?php

namespace mtphp\Database\Event;

use mtphp\Database\ORM\EntityManager;
use mtphp\EventManager\Event;

/**
 * Class PostFlushEvent
 * @package mtphp\Database\Event
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