<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PreRemoveEvent extends AbstractEntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $em
     */
    public function __construct(Object $entity, EntityManagerInterface $em)
    {
        parent::__construct($entity, $em, DbEvents::preRemove);
    }
}
