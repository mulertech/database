<?php

namespace MulerTech\Database\Tests\Files;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EntityRepository;
use MulerTech\Database\Tests\Files\Entity\User;

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