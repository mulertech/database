<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\EventManager\Event;

/**
 * Class PostFlushEvent.
 *
 * @author Sébastien Muler
 */
class PostFlushEvent extends Event
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->setName(DbEvents::postFlush->value);
        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
