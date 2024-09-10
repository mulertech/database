<?php

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * Class PreRemoveEvent
 * @package MulerTech\Database\Event
 * @author Sébastien Muler
 */
class PreRemoveEvent extends EntityEvent
{
    /**
     * @param Object $entity
     * @param EntityManagerInterface $em
     */
    public function __construct(Object $entity, EntityManagerInterface $em) {
        $this->setName(DbEvents::preRemove->value);
        parent::__construct($entity, $em);
    }
}