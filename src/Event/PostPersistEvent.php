<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PostPersistEvent extends AbstractEntityEvent
{
    /**
     * @param object $entity
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::postPersist);
    }
}
