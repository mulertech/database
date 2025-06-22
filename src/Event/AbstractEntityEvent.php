<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\EventManager\Event;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
abstract class AbstractEntityEvent extends Event
{
    /**
     * @var object
     */
    protected object $entity;
    /**
     * @var EntityManagerInterface
     */
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
