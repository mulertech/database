<?php

namespace MulerTech\Database\Tests\Files\Repository;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\Repository\EntityRepository;
use MulerTech\FileManipulation\Tests\Files\Entity\ParentUser;

class ParentUserRepository extends EntityRepository
{
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, ParentUser::class);
    }
}