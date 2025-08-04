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

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityRepositoryTest extends TestCase
{
    private EntityManager $entityManager;
    private EntityRepository $repository;

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

        $pdo = $this->entityManager->getPdm();
        $query = 'DROP TABLE IF EXISTS users_test';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS users_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255), size INT, unit_id INT UNSIGNED, manager INT UNSIGNED)';
        $pdo->exec($query);

        // Add base test data
        $query = 'INSERT INTO users_test (username, size, unit_id, manager) VALUES 
                  ("john_doe", 180, 1, 1), 
                  ("jane_smith", 165, 2, 1), 
                  ("bob_wilson", 175, 1, 2),
                  ("alice_brown", 170, 3, 2)';
        $pdo->exec($query);

        $this->repository = $this->entityManager->getRepository(User::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $query = 'DROP TABLE IF EXISTS users_test';
        $this->entityManager->getPdm()->exec($query);
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
        // Test with non-existent ID
        $result = $this->repository->find(999);
        self::assertNull($result);

        // Test with existing ID
        $result = $this->repository->find(1);
        self::assertNotNull($result);
        self::assertInstanceOf(User::class, $result);
    }

    public function testFindBy(): void
    {
        // Test with non-existent criteria
        $result = $this->repository->findBy(['username' => 'NonExistentUser']);
        self::assertIsArray($result);
        self::assertEmpty($result);

        // Test with existing criteria
        $result = $this->repository->findBy(['unit_id' => 1]);
        self::assertIsArray($result);
        self::assertCount(2, $result);
    }

    public function testFindOneBy(): void
    {
        // Test with non-existent criteria
        $result = $this->repository->findOneBy(['username' => 'NonExistentUser']);
        self::assertNull($result);

        // Test with existing criteria
        $result = $this->repository->findOneBy(['username' => 'john_doe']);
        self::assertNotNull($result);
        self::assertInstanceOf(User::class, $result);
    }

    public function testFindAll(): void
    {
        $result = $this->repository->findAll();
        
        self::assertIsArray($result);
        self::assertCount(4, $result);
    }

    public function testCount(): void
    {
        $count = $this->repository->count();
        
        self::assertIsInt($count);
        self::assertEquals(4, $count);
    }

    public function testCountWithCriteria(): void
    {
        // Test with non-matching criteria
        $count = $this->repository->count(['username' => 'NonExistentUser']);
        self::assertEquals(0, $count);

        // Test with matching criteria
        $count = $this->repository->count(['manager' => 1]);
        self::assertEquals(2, $count);
    }

    public function testGetEntityManager(): void
    {
        $em = $this->repository->getEntityManager();
        
        self::assertSame($this->entityManager, $em);
    }

    public function testFindWithComplexCriteria(): void
    {
        // Test with non-matching criteria
        $criteria = ['username' => 'John', 'size' => 999];
        $result = $this->repository->findBy($criteria);
        self::assertIsArray($result);
        self::assertEmpty($result);

        // Test with matching criteria
        $criteria = ['unit_id' => 1, 'manager' => 1];
        $result = $this->repository->findBy($criteria);
        self::assertIsArray($result);
        self::assertCount(1, $result);
    }

    public function testFindOneWithComplexCriteria(): void
    {
        // Test with non-matching criteria
        $criteria = ['username' => 'John', 'size' => 999];
        $result = $this->repository->findOneBy($criteria);
        self::assertNull($result);

        // Test with matching criteria
        $criteria = ['unit_id' => 2, 'manager' => 1];
        $result = $this->repository->findOneBy($criteria);
        self::assertNotNull($result);
        self::assertInstanceOf(User::class, $result);
    }

    public function testFindWithOrderBy(): void
    {
        $result = $this->repository->findBy([], ['username' => 'ASC']);
        
        self::assertIsArray($result);
        self::assertCount(4, $result);
        // Verify ordering - check that the first result has the expected username
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('alice_brown', $result[0]->getUsername());
    }

    public function testFindWithLimit(): void
    {
        $result = $this->repository->findBy([], null, 2);

        self::assertIsArray($result);
        self::assertCount(2, $result);
    }

    public function testFindWithOffset(): void
    {
        $allResults = $this->repository->findBy([]);
        $offsetResults = $this->repository->findBy([], null, 2, 2);

        self::assertIsArray($offsetResults);
        self::assertCount(2, $offsetResults);
        self::assertLessThan(count($allResults), count($offsetResults));
    }

    public function testFindWithLimitAndOffset(): void
    {
        $result = $this->repository->findBy([], null, 2, 1);

        self::assertIsArray($result);
        self::assertCount(2, $result);
    }

    public function testRepositoryInheritance(): void
    {
        self::assertInstanceOf(EntityRepository::class, $this->repository);
        self::assertInstanceOf(UserRepository::class, $this->repository);
    }

    public function testFindByCriteriaMethods(): void
    {
        // Test with non-existent data
        $users = $this->repository->findByUsername('NonExistentUser');
        self::assertIsArray($users);
        self::assertEmpty($users);

        $users = $this->repository->findBySize(999);
        self::assertIsArray($users);
        self::assertEmpty($users);

        // Test with existing data
        $users = $this->repository->findByUsername('john_doe');
        self::assertIsArray($users);
        self::assertCount(1, $users);

        $users = $this->repository->findBySize(180);
        self::assertIsArray($users);
        self::assertCount(1, $users);
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
        self::assertContains('findBySize', $methods);
    }

    public function testFindWithNullCriteria(): void
    {
        $result = $this->repository->findBy([]);
        
        self::assertIsArray($result);
        self::assertCount(4, $result);
    }

    public function testCountWithEmptyCriteria(): void
    {
        $count = $this->repository->count([]);
        
        self::assertIsInt($count);
        self::assertEquals(4, $count);
    }

    public function testCountWithMultipleCriteria(): void
    {
        // Test with non-matching criteria
        $count = $this->repository->count(['username' => 'john_doe', 'size' => 999]);
        self::assertEquals(0, $count);

        // Test with matching criteria
        $count = $this->repository->count(['username' => 'john_doe', 'size' => 180]);
        self::assertEquals(1, $count);
    }

    public function testFindWithStringId(): void
    {
        $result = $this->repository->find('string-id');
        
        self::assertNull($result);
    }

    public function testFindOneByWithNullResult(): void
    {
        // Test with non-existent ID
        $result = $this->repository->findOneBy(['id' => 999999]);
        self::assertNull($result);

        // Test with existing ID
        $result = $this->repository->findOneBy(['id' => 1]);
        self::assertNotNull($result);
        self::assertInstanceOf(User::class, $result);
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

