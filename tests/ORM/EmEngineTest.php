<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\EventManager\EventManager;
use PHPUnit\Framework\TestCase;

class EmEngineTest extends TestCase
{
    private EmEngine $emEngine;
    private EntityManager $entityManager;

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
        
        $this->emEngine = $this->entityManager->getEmEngine();
        $pdo = $this->entityManager->getPdm();
        $query = 'DROP TABLE IF EXISTS users_test';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS users_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255), size INT, unit_id INT UNSIGNED, manager INT UNSIGNED)';
        $pdo->exec($query);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $query = 'DROP TABLE IF EXISTS users_test';
        $this->entityManager->getPdm()->exec($query);
    }

    public function testPersist(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        
        $metadata = $this->emEngine->getIdentityMap()->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::NEW, $metadata->state);
    }

    public function testRemove(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        $this->emEngine->remove($user);
        
        $metadata = $this->emEngine->getIdentityMap()->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::REMOVED, $metadata->state);
    }

    public function testMerge(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $mergedUser = $this->emEngine->merge($user);
        
        self::assertSame($user, $mergedUser);
        
        $metadata = $this->emEngine->getIdentityMap()->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata->state);
    }

    public function testDetach(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        
        $metadata = $this->emEngine->getIdentityMap()->getMetadata($user);
        self::assertNotNull($metadata);
        
        $this->emEngine->detach($user);
        
        $metadata = $this->emEngine->getIdentityMap()->getMetadata($user);
        self::assertNull($metadata);
    }

    public function testFind(): void
    {
        $result = $this->emEngine->find(User::class, 999);
        
        self::assertNull($result);
    }

    public function testFindWithStringCriteria(): void
    {
        $result = $this->emEngine->find(User::class, 'username=\'NonExistent\'');
        
        self::assertNull($result);
    }

    public function testHasChanges(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        self::assertFalse($this->emEngine->hasChanges($user));
        
        $this->emEngine->persist($user);
        
        $user->setUsername('Jane');
        
        self::assertTrue($this->emEngine->hasChanges($user));
    }

    public function testGetChangeSet(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        
        $changeSet = $this->emEngine->getChangeSet($user);
        self::assertNull($changeSet);
        
        $user->setUsername('Jane');
        
        $changeSet = $this->emEngine->getChangeSet($user);
        self::assertNotNull($changeSet);
        self::assertFalse($changeSet->isEmpty());
    }

    public function testClear(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user2 = new User();
        $user2->setUsername('Jane');
        
        $this->emEngine->persist($user1);
        $this->emEngine->persist($user2);
        
        self::assertNotNull($this->emEngine->getIdentityMap()->getMetadata($user1));
        self::assertNotNull($this->emEngine->getIdentityMap()->getMetadata($user2));
        
        $this->emEngine->clear();
        
        self::assertNull($this->emEngine->getIdentityMap()->getMetadata($user1));
        self::assertNull($this->emEngine->getIdentityMap()->getMetadata($user2));
    }

    public function testIsManaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        self::assertFalse($this->emEngine->isManaged($user));
        
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::MANAGED,
            [],
            new \DateTimeImmutable()
        );
        
        $this->emEngine->getIdentityMap()->add($user, 1, $entityState);
        
        self::assertTrue($this->emEngine->isManaged($user));
    }

    public function testIsNew(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        self::assertFalse($this->emEngine->isNew($user));
        
        $this->emEngine->persist($user);
        
        self::assertTrue($this->emEngine->isNew($user));
    }

    public function testIsRemoved(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        self::assertFalse($this->emEngine->isRemoved($user));
        
        $this->emEngine->remove($user);
        
        self::assertTrue($this->emEngine->isRemoved($user));
    }

    public function testIsDetached(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        self::assertFalse($this->emEngine->isDetached($user));
        
        $this->emEngine->detach($user);
        
        self::assertTrue($this->emEngine->isDetached($user));
    }

    public function testGetIdentityMap(): void
    {
        $identityMap = $this->emEngine->getIdentityMap();
        
        self::assertNotNull($identityMap);
        self::assertInstanceOf(\MulerTech\Database\ORM\IdentityMap::class, $identityMap);
    }

    public function testRefresh(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::MANAGED,
            ['username' => 'OriginalName'],
            new \DateTimeImmutable()
        );
        
        $this->emEngine->getIdentityMap()->add($user, 123, $entityState);
        
        $this->emEngine->refresh($user);
        
        $metadata = $this->emEngine->getIdentityMap()->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata->state);
    }

    public function testFlush(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        
        $this->emEngine->flush();
        
        $metadata = $this->emEngine->getIdentityMap()->getMetadata($user);
        self::assertNotNull($metadata);
    }

    public function testPersistWithRelations(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        
        $this->emEngine->persist($user);
        
        $userMetadata = $this->emEngine->getIdentityMap()->getMetadata($user);
        self::assertNotNull($userMetadata);
        self::assertEquals(EntityLifecycleState::NEW, $userMetadata->state);
    }

    public function testComputeChangeSets(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        
        $user->setUsername('Jane');
        
        $this->emEngine->computeChangeSets();
        
        $changeSet = $this->emEngine->getChangeSet($user);
        self::assertNotNull($changeSet);
        self::assertFalse($changeSet->isEmpty());
    }

    public function testGetScheduledInsertions(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->emEngine->persist($user);
        
        $insertions = $this->emEngine->getScheduledInsertions();
        
        self::assertIsArray($insertions);
        self::assertContains($user, $insertions);
    }

    public function testGetScheduledUpdates(): void
    {
        $updates = $this->emEngine->getScheduledUpdates();
        
        self::assertIsArray($updates);
    }

    public function testGetScheduledDeletions(): void
    {
        $deletions = $this->emEngine->getScheduledDeletions();
        
        self::assertIsArray($deletions);
    }

    public function testGetQueryBuilderObjectResultWithDatabaseResult(): void
    {
        $pdo = $this->entityManager->getPdm();
        $stmt = $pdo->prepare('INSERT INTO users_test (username, size) VALUES (?, ?)');
        $stmt->execute(['TestUser', 100]);
        $userId = $pdo->lastInsertId();
        
        $queryBuilder = new \MulerTech\Database\Query\Builder\QueryBuilder($this->emEngine)
            ->select('*')
            ->from('users_test')
            ->where('id', $userId);
            
        $result = $this->emEngine->getQueryBuilderObjectResult($queryBuilder, User::class);
        
        self::assertNotNull($result);
        self::assertInstanceOf(User::class, $result);
        self::assertEquals('TestUser', $result->getUsername());
        self::assertEquals(100, $result->getSize());
    }

    public function testGetQueryBuilderObjectResultWithEmptyResult(): void
    {
        $queryBuilder = new \MulerTech\Database\Query\Builder\QueryBuilder($this->emEngine)
            ->select('*')
            ->from('users_test')
            ->where('id', 999999);
            
        $result = $this->emEngine->getQueryBuilderObjectResult($queryBuilder, User::class);
        
        self::assertNull($result);
    }

    public function testGetQueryBuilderListResult(): void
    {
        $pdo = $this->entityManager->getPdm();
        $stmt = $pdo->prepare('INSERT INTO users_test (username, size) VALUES (?, ?)');
        $stmt->execute(['User1', 100]);
        $stmt->execute(['User2', 200]);
        
        $queryBuilder = new \MulerTech\Database\Query\Builder\QueryBuilder($this->emEngine)
            ->select('*')
            ->from('users_test');
            
        $result = $this->emEngine->getQueryBuilderListResult($queryBuilder, User::class);
        
        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertInstanceOf(User::class, $result[1]);
    }

    public function testGetQueryBuilderListResultEmpty(): void
    {
        $queryBuilder = new \MulerTech\Database\Query\Builder\QueryBuilder($this->emEngine)
            ->select('*')
            ->from('users_test')
            ->where('id', 999999);
            
        $result = $this->emEngine->getQueryBuilderListResult($queryBuilder, User::class);
        
        self::assertNull($result);
    }

    public function testCreateManagedEntity(): void
    {
        $entityData = [
            'id' => 1,
            'username' => 'TestUser',
            'size' => 100
        ];
        
        $entity = $this->emEngine->createManagedEntity($entityData, User::class, false);
        
        self::assertInstanceOf(User::class, $entity);
        self::assertEquals('TestUser', $entity->getUsername());
        self::assertEquals(100, $entity->getSize());
        self::assertTrue($this->emEngine->isManaged($entity));
    }

    public function testRowCount(): void
    {
        $pdo = $this->entityManager->getPdm();
        $stmt = $pdo->prepare('INSERT INTO users_test (username, size) VALUES (?, ?)');
        $stmt->execute(['User1', 100]);
        $stmt->execute(['User2', 200]);
        
        $count = $this->emEngine->rowCount(User::class);
        
        self::assertEquals(2, $count);
    }

    public function testRowCountWithNumericFilter(): void
    {
        $pdo = $this->entityManager->getPdm();
        $stmt = $pdo->prepare('INSERT INTO users_test (username, size) VALUES (?, ?)');
        $stmt->execute(['User1', 100]);
        $userId = $pdo->lastInsertId();
        
        $count = $this->emEngine->rowCount(User::class, (string)$userId);
        
        self::assertEquals(1, $count);
    }

    public function testRowCountWithStringFilter(): void
    {
        $pdo = $this->entityManager->getPdm();
        $stmt = $pdo->prepare('INSERT INTO users_test (username, size) VALUES (?, ?)');
        $stmt->execute(['User1', 100]);
        $userId = $pdo->lastInsertId();
        
        $count = $this->emEngine->rowCount(User::class, 'abc');
        
        self::assertIsInt($count);
    }

    public function testPersistEntityWithExistingId(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('ExistingUser');
        
        $this->emEngine->persist($user);
        
        self::assertTrue($this->emEngine->isManaged($user));
    }

    public function testGetChangesMethod(): void
    {
        $user = new User();
        $user->setUsername('OriginalName');
        
        $this->emEngine->persist($user);
        
        $changes = $this->emEngine->getChanges($user);
        self::assertIsArray($changes);
        
        $user->setUsername('NewName');
        
        $changes = $this->emEngine->getChanges($user);
        self::assertIsArray($changes);
    }

    public function testMergeWithExistingManagedEntity(): void
    {
        $user1 = new User();
        $user1->setId(456);
        $user1->setUsername('FirstUser');
        
        $this->emEngine->getIdentityMap()->add($user1);
        
        $user2 = new User();
        $user2->setId(456);
        $user2->setUsername('SecondUser');
        
        $mergedUser = $this->emEngine->merge($user2);
        
        self::assertSame($user1, $mergedUser);
        self::assertNotSame($user2, $mergedUser);
    }

    public function testRefreshWithoutId(): void
    {
        $user = new User();
        $user->setUsername('NoIdUser');
        
        $this->emEngine->refresh($user);
        
        // Should not throw exception and should handle gracefully
        self::assertNull($user->getId());
    }

    public function testFindWithWhereClause(): void
    {
        $pdo = $this->entityManager->getPdm();
        $stmt = $pdo->prepare('INSERT INTO users_test (username, size) VALUES (?, ?)');
        $stmt->execute(['FindUser', 150]);
        
        $result = $this->emEngine->find(User::class, "username = 'FindUser'");
        
        self::assertInstanceOf(User::class, $result);
        self::assertEquals('FindUser', $result->getUsername());
    }

    public function testFindWithNumericStringId(): void
    {
        $pdo = $this->entityManager->getPdm();
        $stmt = $pdo->prepare('INSERT INTO users_test (username, size) VALUES (?, ?)');
        $stmt->execute(['NumericUser', 175]);
        $userId = $pdo->lastInsertId();
        
        $result = $this->emEngine->find(User::class, (string)$userId);
        
        self::assertInstanceOf(User::class, $result);
        self::assertEquals('NumericUser', $result->getUsername());
    }

    public function testFindReturnsIdentityMapCachedEntity(): void
    {
        $user = new User();
        $user->setId(789);
        $user->setUsername('CachedUser');
        
        $this->emEngine->getIdentityMap()->add($user);
        
        $result = $this->emEngine->find(User::class, 789);
        
        self::assertSame($user, $result);
    }
}