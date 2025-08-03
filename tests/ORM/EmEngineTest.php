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
}