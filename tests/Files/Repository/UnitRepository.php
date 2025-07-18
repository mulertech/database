<?php

namespace MulerTech\Database\Tests\Files\Repository;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\Repository\EntityRepository;
use MulerTech\Database\Tests\Files\Entity\Unit;

class UnitRepository extends EntityRepository
{
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, Unit::class);
    }

}