<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\EventManager\Event;

/**
 * @author Sébastien Muler
 */
abstract class AbstractEntityEvent extends Event
{
    protected object $entity;
    protected EntityManagerInterface $entityManager;

    public function __construct(object $entity, EntityManagerInterface $entityManager, DbEvents $eventName)
    {
        $this->setName($eventName->value);
        $this->entity = $entity;
        $this->entityManager = $entityManager;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
