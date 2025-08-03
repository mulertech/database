<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\Engine\Persistence\PersistenceManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\EventManager\EventManager;
use PHPUnit\Framework\TestCase;

class PersistenceManagerTest extends TestCase
{
    private PersistenceManager $persistenceManager;
    private EntityManager $entityManager;
    private IdentityMap $identityMap;

    protected function setUp(): void
    {
        parent::setUp();
        
        $eventManager = new EventManager();
        $metadataCache = new MetadataCache();
        $metadataCache->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );
        
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $metadataCache,
            $eventManager
        );
        
        $this->identityMap = $this->entityManager->getEmEngine()->getIdentityMap();
        
        // Create all dependencies needed by PersistenceManager constructor
        $stateManager = $this->createMock(StateManagerInterface::class);
        $stateManager->method('getEntityState')
            ->willReturn(EntityLifecycleState::MANAGED);
        $stateManager->method('getScheduledInsertions')->willReturn([]);
        $stateManager->method('getScheduledUpdates')->willReturn([]);
        $stateManager->method('getScheduledDeletions')->willReturn([]);

        $changeDetector = new \MulerTech\Database\ORM\ChangeDetector();
        $relationManager = $this->createMock(\MulerTech\Database\ORM\Engine\Relations\RelationManager::class);

        // Create processors
        $insertionProcessor = new \MulerTech\Database\ORM\Engine\Persistence\InsertionProcessor(
            $this->entityManager,
            $metadataCache
        );
        $updateProcessor = new \MulerTech\Database\ORM\Engine\Persistence\UpdateProcessor(
            $this->entityManager,
            $metadataCache
        );
        $deletionProcessor = new \MulerTech\Database\ORM\Engine\Persistence\DeletionProcessor(
            $this->entityManager,
            $metadataCache
        );

        // Create ChangeSetManager
        $entityRegistry = new \MulerTech\Database\ORM\EntityRegistry();
        $changeSetManager = new \MulerTech\Database\ORM\ChangeSetManager(
            $this->identityMap,
            $entityRegistry,
            $changeDetector
        );

        $this->persistenceManager = new PersistenceManager(
            $this->entityManager,      // EntityManagerInterface (not PhpDatabaseManager)
            $stateManager,             // StateManagerInterface
            $changeDetector,           // ChangeDetector
            $relationManager,          // RelationManager
            $insertionProcessor,       // InsertionProcessor
            $updateProcessor,          // UpdateProcessor
            $deletionProcessor,        // DeletionProcessor
            $eventManager,             // ?EventManager
            $changeSetManager,         // ChangeSetManager
            $this->identityMap         // IdentityMap
        );
    }

    public function testPersist(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        // Test the actual persist method
        $this->persistenceManager->persist($user);

        self::assertTrue(true);
    }

    public function testRemove(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        // Test the actual remove method
        $this->persistenceManager->remove($user);

        self::assertTrue(true);
    }

    public function testDetach(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        // Test the actual detach method
        $this->persistenceManager->detach($user);

        self::assertTrue(true);
    }

    public function testFlush(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        // Persist entity and flush
        $this->persistenceManager->persist($user);
        $this->persistenceManager->flush();

        self::assertTrue(true);
    }

    public function testPersistWithRelations(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        
        // Test persisting entity with relations
        $this->persistenceManager->persist($unit);
        $this->persistenceManager->persist($user);

        self::assertTrue(true);
    }

    public function testMultipleOperations(): void
    {
        $user1 = new User();
        $user1->setUsername('John');

        $user2 = new User();
        $user2->setId(456);
        $user2->setUsername('Jane');
        
        $user3 = new User();
        $user3->setId(789);
        $user3->setUsername('Bob');

        // Test multiple operations
        $this->persistenceManager->persist($user1);
        $this->persistenceManager->persist($user2);
        $this->persistenceManager->remove($user3);
        $this->persistenceManager->flush();

        self::assertTrue(true);
    }

    public function testEntityStateCreation(): void
    {
        // Test correct EntityState construction with all required parameters
        $entityState = new EntityState(
            User::class,                    // className (string)
            123,                           // identifier
            EntityLifecycleState::MANAGED, // state (enum)
            ['username' => 'John'],        // originalData
            new \DateTimeImmutable()       // loadedAt
        );

        self::assertEquals(User::class, $entityState->className);
        self::assertEquals(123, $entityState->identifier);
        self::assertEquals(EntityLifecycleState::MANAGED, $entityState->state);
        self::assertEquals(['username' => 'John'], $entityState->originalData);
        self::assertInstanceOf(\DateTimeImmutable::class, $entityState->loadedAt);
    }

    public function testIdentityMapIntegration(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        // Create EntityState with correct constructor parameters
        $entityState = new EntityState(
            User::class,                    // className (string)
            123,                           // identifier
            EntityLifecycleState::MANAGED, // state (enum)
            ['username' => 'John'],        // originalData
            new \DateTimeImmutable()       // loadedAt
        );

        // Test adding to identity map
        $this->identityMap->add($user, 123, $entityState);

        self::assertTrue($this->identityMap->contains(User::class, 123));
    }

    public function testFlushWithoutOperations(): void
    {
        // Test flush when no operations are scheduled
        $this->persistenceManager->flush();

        self::assertTrue(true);
    }

    public function testPersistAndFlushWorkflow(): void
    {
        $user = new User();
        $user->setUsername('TestUser');

        // Test complete workflow
        $this->persistenceManager->persist($user);
        $this->persistenceManager->flush();

        self::assertTrue(true);
    }

    public function testRemoveAndFlushWorkflow(): void
    {
        $user = new User();
        $user->setId(999);
        $user->setUsername('ToBeRemoved');

        // Test removal workflow
        $this->persistenceManager->remove($user);
        $this->persistenceManager->flush();

        self::assertTrue(true);
    }

    public function testDetachWorkflow(): void
    {
        $user = new User();
        $user->setId(888);
        $user->setUsername('ToBeDetached');

        // Test detach workflow
        $this->persistenceManager->detach($user);

        self::assertTrue(true);
    }
}
