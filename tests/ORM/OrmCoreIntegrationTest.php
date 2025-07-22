<?php

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityFactory;
use MulerTech\Database\ORM\EntityHydrator;
use MulerTech\Database\ORM\EntityMetadata;
use MulerTech\Database\ORM\EntityRegistry;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\EntityState;
use PHPUnit\Framework\TestCase;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class OrmCoreIntegrationTest extends TestCase
{
    private IdentityMap $identityMap;
    private ChangeDetector $changeDetector;
    private ChangeSetManager $changeSetManager;
    private EntityFactory $entityFactory;

    protected function setUp(): void
    {
        $this->identityMap = new IdentityMap();
        $this->changeDetector = new ChangeDetector();
        $this->changeSetManager = new ChangeSetManager($this->identityMap, new EntityRegistry(), $this->changeDetector);
        $this->entityFactory = new EntityFactory(
            new EntityHydrator(
                new DbMapping(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity')),
                $this->identityMap
        );
    }

    public function testIdentityMapBasicOperations(): void
    {
        $user = new TestUser(1, 'John Doe', 'john@example.com');

        // Test add and contains
        $this->identityMap->add($user);
        $this->assertTrue($this->identityMap->contains(TestUser::class, 1));
        $this->assertFalse($this->identityMap->contains(TestUser::class, 2));

        // Test get
        $retrievedUser = $this->identityMap->get(TestUser::class, 1);
        $this->assertSame($user, $retrievedUser);

        // Test metadata
        $metadata = $this->identityMap->getMetadata($user);
        $this->assertNotNull($metadata);
        $this->assertEquals(TestUser::class, $metadata->className);
        $this->assertEquals(1, $metadata->identifier);
        $this->assertEquals(EntityState::MANAGED, $metadata->state);
    }

    public function testChangeDetection(): void
    {
        $user = new TestUser(1, 'John Doe', 'john@example.com');
        $originalData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        // No changes initially
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        $this->assertTrue($changeSet->isEmpty());

        // Make changes
        $user->setName('Jane Doe');
        $user->setEmail('jane@example.com');

        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        $this->assertFalse($changeSet->isEmpty());
        $this->assertEquals(2, $changeSet->getChangeCount());
        $this->assertTrue($changeSet->hasChangedField('name'));
        $this->assertTrue($changeSet->hasChangedField('email'));

        $nameChange = $changeSet->getFieldChange('name');
        $this->assertNotNull($nameChange);
        $this->assertEquals('John Doe', $nameChange->oldValue);
        $this->assertEquals('Jane Doe', $nameChange->newValue);
    }

    public function testChangeSetManager(): void
    {
        $user = new TestUser(null, 'John Doe', 'john@example.com');

        // Schedule insertion
        $this->changeSetManager->scheduleInsert($user);
        $insertions = $this->changeSetManager->getScheduledInsertions();
        $this->assertCount(1, $insertions);
        $this->assertSame($user, $insertions[0]);

        // Check entity state
        $this->assertEquals(EntityState::NEW, $this->identityMap->getEntityState($user));

        // Create another user for update test
        $user2 = new TestUser(2, 'Alice', 'alice@example.com');
        $this->identityMap->add($user2);

        // Modify and schedule update
        $user2->setName('Alice Smith');
        $this->changeSetManager->scheduleUpdate($user2);

        // Compute changesets
        $this->changeSetManager->computeChangeSets();

        $updates = $this->changeSetManager->getScheduledUpdates();
        $this->assertCount(1, $updates);

        $changeSet = $this->changeSetManager->getChangeSet($user2);
        $this->assertNotNull($changeSet);
        $this->assertTrue($changeSet->hasChangedField('name'));
    }

    public function testEntityFactory(): void
    {
        $data = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        // Create entity
        $user = $this->entityFactory->create(TestUser::class, $data);
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals(1, $user->getId());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());

        // Test extract
        $extractedData = $this->entityFactory->extract($user);
        $this->assertEquals($data['id'], $extractedData['id']);
        $this->assertEquals($data['name'], $extractedData['name']);
        $this->assertEquals($data['email'], $extractedData['email']);
    }

    public function testCompleteWorkflow(): void
    {
        // 1. Create entities using factory
        $user1 = $this->entityFactory->create(TestUser::class, [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $user2 = $this->entityFactory->create(TestUser::class, [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);

        // 2. Schedule operations
        $this->changeSetManager->scheduleInsert($user2);

        // Add user1 to identity map for update test
        $this->identityMap->add($user1);

        // 3. Modify existing entity
        $user1->setEmail('john.doe@example.com');
        $this->changeSetManager->scheduleUpdate($user1);

        // 4. Compute all changes
        $this->changeSetManager->computeChangeSets();

        // 5. Verify state
        $this->assertTrue($this->changeSetManager->hasPendingChanges());

        $statistics = $this->changeSetManager->getStatistics();
        $this->assertEquals(1, $statistics['insertions']);
        $this->assertEquals(1, $statistics['updates']);
        $this->assertEquals(0, $statistics['deletions']);
        $this->assertEquals(1, $statistics['changeSets']);

        // 6. Check specific changes
        $changeSet = $this->changeSetManager->getChangeSet($user1);
        $this->assertNotNull($changeSet);
        $this->assertTrue($changeSet->hasChangedField('email'));

        $emailChange = $changeSet->getFieldChange('email');
        $this->assertEquals('john@example.com', $emailChange->oldValue);
        $this->assertEquals('john.doe@example.com', $emailChange->newValue);
    }

    public function testWeakReferenceCleanup(): void
    {
        $user = new TestUser(1, 'John Doe', 'john@example.com');
        $this->identityMap->add($user);

        $this->assertTrue($this->identityMap->contains(TestUser::class, 1));

        // Remove strong reference
        unset($user);

        // Force garbage collection
        gc_collect_cycles();

        // Cleanup should remove dead references
        $this->identityMap->cleanup();

        // Entity should no longer be in identity map
        $this->assertFalse($this->identityMap->contains(TestUser::class, 1));
    }

    public function testPerformanceWithLargeDataset(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Create 1000 entities
        $users = [];
        for ($i = 1; $i <= 1000; $i++) {
            $user = new TestUser($i, "User $i", "user$i@example.com");
            $users[] = $user; // Garder une référence
            $this->identityMap->add($user);

            // S'assurer que les données originales sont capturées correctement
            $metadata = $this->identityMap->getMetadata($user);
            if ($metadata !== null) {
                $currentData = $this->changeDetector->extractCurrentData($user);
                $updatedMetadata = new EntityMetadata(
                    className: $metadata->className,
                    identifier: $metadata->identifier,
                    state: EntityState::MANAGED,
                    originalData: $currentData,
                    loadedAt: $metadata->loadedAt,
                    lastModified: new \DateTimeImmutable()
                );
                $this->identityMap->updateMetadata($user, $updatedMetadata);
            }
        }

        // Modify half of them
        for ($i = 0; $i < 500; $i++) {
            $user = $users[$i];
            $user->setName("Modified User " . ($i + 1));
            // Explicitement programmer la mise à jour
            $this->changeSetManager->scheduleUpdate($user);
        }

        // Compute all changes
        $this->changeSetManager->computeChangeSets();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        // Performance assertions
        $this->assertLessThan(1.0, $executionTime, 'Processing 1000 entities should take less than 1 second');
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be reasonable');

        $statistics = $this->changeSetManager->getStatistics();
        $this->assertEquals(500, $statistics['updates']);

        $changedEntities = 0;
        foreach ($users as $user) {
            if ($this->changeSetManager->hasChanges($user)) {
                $changedEntities++;
            }
        }
        $this->assertEquals(500, $changedEntities);
    }
}

/**
 * Test entity class for unit tests
 */
class TestUser
{
    public function __construct(
        private ?int $id = null,
        private string $name = '',
        private string $email = ''
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
}