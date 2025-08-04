<?php

namespace MulerTech\Database\Tests\Files\Repository;

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

    /**
     * @param string $username
     * @return array<User>
     */
    public function findByUsername(string $username): array
    {
        return $this->findBy(['username' => $username]);
    }

    /**
     * @param int $size
     * @return array<User>
     */
    public function findBySize(int $size): array
    {
        return $this->findBy(['size' => $size]);
    }
}