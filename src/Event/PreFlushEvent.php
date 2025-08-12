<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\EventManager\Event;

/**
 * Class PreFlushEvent
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PreFlushEvent extends Event
{
    /**
     * @var EntityManagerInterface $entityManager
     */
    private EntityManagerInterface $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->setName(DbEvents::preFlush->value);
        $this->entityManager = $entityManager;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
