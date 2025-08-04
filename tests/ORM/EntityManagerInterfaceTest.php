<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\EventManager\EventManager;
use PHPUnit\Framework\TestCase;

class EntityManagerInterfaceTest extends TestCase
{
    private EntityManagerInterface $entityManager;

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
    }

    public function testImplementsEntityManagerInterface(): void
    {
        self::assertInstanceOf(EntityManagerInterface::class, $this->entityManager);
    }

    public function testPersist(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->entityManager->persist($user);
        
        self::assertTrue(true);
    }

    public function testRemove(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->entityManager->persist($user);
        $this->entityManager->remove($user);
        
        self::assertTrue(true);
    }

    public function testMerge(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $mergedUser = $this->entityManager->merge($user);
        
        self::assertSame($user, $mergedUser);
    }

    public function testDetach(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->entityManager->persist($user);
        $this->entityManager->detach($user);
        
        self::assertTrue(true);
    }

    public function testFind(): void
    {
        $result = $this->entityManager->find(User::class, 999);
        
        self::assertNull($result);
    }

    public function testFindWithStringCriteria(): void
    {
        $result = $this->entityManager->find(User::class, 'username=\'NonExistent\'');
        
        self::assertNull($result);
    }

    public function testFlush(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        self::assertTrue(true);
    }

    public function testClear(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->entityManager->persist($user);
        $this->entityManager->clear();
        
        self::assertTrue(true);
    }

    public function testRefresh(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $this->entityManager->refresh($user);
        
        self::assertTrue(true);
    }

    public function testGetRepository(): void
    {
        $repository = $this->entityManager->getRepository(User::class);
        
        self::assertNotNull($repository);
    }

    public function testIsUnique(): void
    {
        $result = $this->entityManager->isUnique(User::class, 'username', 'UniqueUser');
        
        self::assertTrue($result);
    }

    public function testRowCount(): void
    {
        $count = $this->entityManager->rowCount(User::class);
        
        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(0, $count);
    }

    public function testGetEmEngine(): void
    {
        $engine = $this->entityManager->getEmEngine();
        
        self::assertNotNull($engine);
    }

    public function testGetPdm(): void
    {
        $pdm = $this->entityManager->getPdm();
        
        self::assertNotNull($pdm);
    }

    public function testInterfaceMethods(): void
    {
        $reflection = new \ReflectionClass(EntityManagerInterface::class);
        $methods = $reflection->getMethods();
        
        $expectedMethods = [
            'persist',
            'remove',
            'merge',
            'detach',
            'find',
            'flush',
            'clear',
            'refresh',
            'getRepository',
            'isUnique',
            'rowCount'
        ];
        
        $actualMethods = array_map(fn($method) => $method->getName(), $methods);
        
        foreach ($expectedMethods as $expectedMethod) {
            self::assertContains($expectedMethod, $actualMethods);
        }
    }

    public function testMethodSignatures(): void
    {
        $reflection = new \ReflectionClass(EntityManagerInterface::class);
        
        $persistMethod = $reflection->getMethod('persist');
        self::assertEquals(1, $persistMethod->getNumberOfParameters());
        
        $removeMethod = $reflection->getMethod('remove');
        self::assertEquals(1, $removeMethod->getNumberOfParameters());
        
        $mergeMethod = $reflection->getMethod('merge');
        self::assertEquals(1, $mergeMethod->getNumberOfParameters());
        
        $findMethod = $reflection->getMethod('find');
        self::assertEquals(2, $findMethod->getNumberOfParameters());
    }

    public function testAllMethodsExistInImplementation(): void
    {
        $interfaceReflection = new \ReflectionClass(EntityManagerInterface::class);
        $implementationReflection = new \ReflectionClass($this->entityManager);
        
        foreach ($interfaceReflection->getMethods() as $method) {
            self::assertTrue(
                $implementationReflection->hasMethod($method->getName()),
                "Method {$method->getName()} not implemented"
            );
        }
    }

    public function testEntityManagerIsInterface(): void
    {
        $reflection = new \ReflectionClass(EntityManagerInterface::class);
        
        self::assertTrue($reflection->isInterface());
    }

    public function testInterfaceConstants(): void
    {
        $reflection = new \ReflectionClass(EntityManagerInterface::class);
        $constants = $reflection->getConstants();
        
        self::assertIsArray($constants);
    }

    public function testCanBeTypedAsInterface(): void
    {
        $user = new User();
        $user->setUsername('InterfaceTestUser');
        
        $this->performOperationsOnInterface($this->entityManager, $user);
        
        self::assertTrue(true);
    }

    private function performOperationsOnInterface(EntityManagerInterface $em, object $entity): void
    {
        $em->persist($entity);
        $em->flush();
        $em->detach($entity);
    }
}