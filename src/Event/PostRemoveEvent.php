<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * @author Sébastien Muler
 */
class PostRemoveEvent extends AbstractEntityEvent
{
    public function __construct(object $entity, EntityManagerInterface $entityManager)
    {
        parent::__construct($entity, $entityManager, DbEvents::postRemove);
    }
}
