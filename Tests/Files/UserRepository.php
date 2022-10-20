<?php

namespace mtphp\Database\Tests\Files;

use mtphp\Database\ORM\EntityManagerInterface;
use mtphp\Database\ORM\EntityRepository;

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