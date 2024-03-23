<?php

namespace MulerTech\Database\Tests\Files;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, User::class);
    }

}