<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityRepository;
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Repository\UserRepository;
use MulerTech\EventManager\EventManager;
use PHPUnit\Framework\TestCase;

class EntityRepositoryTest extends TestCase
{
    private EntityManager $entityManager;
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $eventManager = new EventManager();
        $metadataCache = new MetadataCache();
        $metadataCache->loadEntitiesFromPath(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );
        
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $metadataCache,
            $eventManager
        );
        
        $this->repository = $this->entityManager->getRepository(User::class);
    }

    public function testGetEntityName(): void
    {
        self::assertEquals(User::class, $this->repository->getEntityName());
    }

    public function testCreateQueryBuilder(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('createQueryBuilder');
        $method->setAccessible(true);
        
        $queryBuilder = $method->invoke($this->repository);
        
        self::assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    public function testFind(): void
    {
        $result = $this->repository->find(999);
        
        self::assertNull($result);
    }

    public function testFindBy(): void
    {
        $result = $this->repository->findBy(['username' => 'NonExistentUser']);
        
        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testFindOneBy(): void
    {
        $result = $this->repository->findOneBy(['username' => 'NonExistentUser']);
        
        self::assertNull($result);
    }

    public function testFindAll(): void
    {
        $result = $this->repository->findAll();
        
        self::assertIsArray($result);
    }

    public function testCount(): void
    {
        $count = $this->repository->count();
        
        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(0, $count);
    }

    public function testCountWithCriteria(): void
    {
        $count = $this->repository->count(['username' => 'NonExistentUser']);
        
        self::assertEquals(0, $count);
    }

    public function testGetEntityManager(): void
    {
        $em = $this->repository->getEntityManager();
        
        self::assertSame($this->entityManager, $em);
    }

    public function testFindWithComplexCriteria(): void
    {
        $criteria = [
            'username' => 'John',
            'age' => 25
        ];
        
        $result = $this->repository->findBy($criteria);
        
        self::assertIsArray($result);
    }

    public function testFindOneWithComplexCriteria(): void
    {
        $criteria = [
            'username' => 'John',
            'age' => 25
        ];
        
        $result = $this->repository->findOneBy($criteria);
        
        self::assertNull($result);
    }

    public function testFindWithOrderBy(): void
    {
        $result = $this->repository->findBy([], ['username' => 'ASC']);
        
        self::assertIsArray($result);
    }

    public function testFindWithLimit(): void
    {
        $result = $this->repository->findBy([], null, 10);
        
        self::assertIsArray($result);
        self::assertLessThanOrEqual(10, count($result));
    }

    public function testFindWithOffset(): void
    {
        $result = $this->repository->findBy([], null, null, 5);
        
        self::assertIsArray($result);
    }

    public function testFindWithLimitAndOffset(): void
    {
        $result = $this->repository->findBy([], null, 5, 10);
        
        self::assertIsArray($result);
        self::assertLessThanOrEqual(5, count($result));
    }

    public function testRepositoryInheritance(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->repository);
        self::assertInstanceOf(UserRepository::class, $this->repository);
    }

    public function testFindByCriteriaMethods(): void
    {
        $users = $this->repository->findByUsername('NonExistentUser');
        
        self::assertIsArray($users);
        self::assertEmpty($users);
    }

    public function testMethodCallForwarding(): void
    {
        $this->expectException(\BadMethodCallException::class);
        
        $this->repository->nonExistentMethod();
    }

    public function testRepositoryCustomMethods(): void
    {
        $methods = get_class_methods($this->repository);
        
        self::assertContains('findByUsername', $methods);
        self::assertContains('findByAge', $methods);
    }

    public function testFindWithNullCriteria(): void
    {
        $result = $this->repository->findBy([]);
        
        self::assertIsArray($result);
    }

    public function testCountWithEmptyCriteria(): void
    {
        $count = $this->repository->count([]);
        
        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(0, $count);
    }

    public function testFindWithStringId(): void
    {
        $result = $this->repository->find('string-id');
        
        self::assertNull($result);
    }

    public function testFindOneByWithNullResult(): void
    {
        $result = $this->repository->findOneBy(['id' => 999999]);
        
        self::assertNull($result);
    }

    public function testRepositoryMethodsReturnTypes(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        
        $findMethod = $reflection->getMethod('find');
        $returnType = $findMethod->getReturnType();
        self::assertNotNull($returnType);
        
        $findAllMethod = $reflection->getMethod('findAll');
        $returnType = $findAllMethod->getReturnType();
        self::assertNotNull($returnType);
    }
}