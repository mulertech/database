<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityRegistry;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\PropertyChange;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class ChangeSetManagerTest extends TestCase
{
    private ChangeSetManager $changeSetManager;
    private IdentityMap $identityMap;
    private EntityRegistry $registry;
    private ChangeDetector $changeDetector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identityMap = new IdentityMap();
        $this->registry = new EntityRegistry();
        $this->changeDetector = new ChangeDetector();
        
        $this->changeSetManager = new ChangeSetManager(
            $this->identityMap,
            $this->registry,
            $this->changeDetector
        );
    }

    public function testScheduleInsert(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->changeSetManager->scheduleInsert($user);
        
        $insertions = $this->changeSetManager->getScheduledInsertions();
        
        self::assertContains($user, $insertions);
    }

    public function testScheduleUpdate(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $this->changeSetManager->scheduleUpdate($user);
        
        $updates = $this->changeSetManager->getScheduledUpdates();
        
        self::assertContains($user, $updates);
    }

    public function testScheduleDelete(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $this->changeSetManager->scheduleDelete($user);
        
        $deletions = $this->changeSetManager->getScheduledDeletions();
        
        self::assertContains($user, $deletions);
    }

    public function testDetach(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        $this->registry->register($user);
        
        $this->changeSetManager->detach($user);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::DETACHED, $metadata->state);
    }

    public function testMerge(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $this->changeSetManager->merge($user);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata->state);
    }

    public function testMergeWithExistingEntity(): void
    {
        $existingUser = new User();
        $existingUser->setId(123);
        $existingUser->setUsername('Original');
        
        $existingState = new EntityState(
            EntityLifecycleState::MANAGED,
            ['username' => 'Original'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($existingUser, 123, $existingState);
        
        $newUser = new User();
        $newUser->setId(123);
        $newUser->setUsername('Updated');
        
        $this->changeSetManager->merge($newUser);
        
        self::assertEquals('Updated', $existingUser->getUsername());
        
        $updates = $this->changeSetManager->getScheduledUpdates();
        self::assertContains($existingUser, $updates);
    }

    public function testMergeWithoutId(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot merge entity without identifier');
        
        $this->changeSetManager->merge($user);
    }

    public function testGetChangeSet(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $propertyChange = new PropertyChange('username', 'Original', 'John');
        $changeSet = new ChangeSet(User::class, ['username' => $propertyChange]);
        
        $reflection = new \ReflectionClass($this->changeSetManager);
        $changeSetsProperty = $reflection->getProperty('changeSets');
        $changeSetsProperty->setAccessible(true);
        $changeSets = $changeSetsProperty->getValue($this->changeSetManager);
        $changeSets[$user] = $changeSet;
        
        $result = $this->changeSetManager->getChangeSet($user);
        
        self::assertSame($changeSet, $result);
    }

    public function testGetChangeSetNotFound(): void
    {
        $user = new User();
        
        $result = $this->changeSetManager->getChangeSet($user);
        
        self::assertNull($result);
    }

    public function testHasChanges(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        self::assertFalse($this->changeSetManager->hasChanges($user));
        
        $propertyChange = new PropertyChange('username', 'Original', 'John');
        $changeSet = new ChangeSet(User::class, ['username' => $propertyChange]);
        
        $reflection = new \ReflectionClass($this->changeSetManager);
        $changeSetsProperty = $reflection->getProperty('changeSets');
        $changeSetsProperty->setAccessible(true);
        $changeSets = $changeSetsProperty->getValue($this->changeSetManager);
        $changeSets[$user] = $changeSet;
        
        self::assertTrue($this->changeSetManager->hasChanges($user));
    }

    public function testHasChangesWithEmptyChangeSet(): void
    {
        $user = new User();
        
        $changeSet = new ChangeSet(User::class, []);
        
        $reflection = new \ReflectionClass($this->changeSetManager);
        $changeSetsProperty = $reflection->getProperty('changeSets');
        $changeSetsProperty->setAccessible(true);
        $changeSets = $changeSetsProperty->getValue($this->changeSetManager);
        $changeSets[$user] = $changeSet;
        
        self::assertFalse($this->changeSetManager->hasChanges($user));
    }

    public function testComputeChangeSets(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            EntityLifecycleState::MANAGED,
            ['username' => 'Original'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $this->changeSetManager->computeChangeSets();
        
        $changeSet = $this->changeSetManager->getChangeSet($user);
        self::assertNotNull($changeSet);
        self::assertFalse($changeSet->isEmpty());
        
        $usernameChange = $changeSet->getFieldChange('username');
        self::assertNotNull($usernameChange);
        self::assertEquals('Original', $usernameChange->oldValue);
        self::assertEquals('John', $usernameChange->newValue);
    }

    public function testComputeChangeSetsWithNewEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, null, $entityState);
        $this->changeSetManager->scheduleInsert($user);
        
        $this->changeSetManager->computeChangeSets();
        
        $insertions = $this->changeSetManager->getScheduledInsertions();
        self::assertContains($user, $insertions);
    }

    public function testClear(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user2 = new User();
        $user2->setUsername('Jane');
        
        $this->changeSetManager->scheduleInsert($user1);
        $this->changeSetManager->scheduleInsert($user2);
        $this->registry->register($user1);
        $this->registry->register($user2);
        
        self::assertNotEmpty($this->changeSetManager->getScheduledInsertions());
        self::assertNotEmpty($this->registry->getRegisteredEntities());
        
        $this->changeSetManager->clear();
        
        self::assertEmpty($this->changeSetManager->getScheduledInsertions());
        self::assertEmpty($this->changeSetManager->getScheduledUpdates());
        self::assertEmpty($this->changeSetManager->getScheduledDeletions());
        self::assertEmpty($this->registry->getRegisteredEntities());
    }

    public function testClearProcessedChanges(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->changeSetManager->scheduleInsert($user);
        
        $propertyChange = new PropertyChange('username', null, 'John');
        $changeSet = new ChangeSet(User::class, ['username' => $propertyChange]);
        
        $reflection = new \ReflectionClass($this->changeSetManager);
        $changeSetsProperty = $reflection->getProperty('changeSets');
        $changeSetsProperty->setAccessible(true);
        $changeSets = $changeSetsProperty->getValue($this->changeSetManager);
        $changeSets[$user] = $changeSet;
        
        self::assertNotEmpty($this->changeSetManager->getScheduledInsertions());
        self::assertNotNull($this->changeSetManager->getChangeSet($user));
        
        $this->changeSetManager->clearProcessedChanges();
        
        self::assertEmpty($this->changeSetManager->getScheduledInsertions());
        self::assertNull($this->changeSetManager->getChangeSet($user));
    }

    public function testMultipleEntityTypes(): void
    {
        $user = new User();
        $user->setUsername('John');
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $this->changeSetManager->scheduleInsert($user);
        $this->changeSetManager->scheduleInsert($unit);
        
        $insertions = $this->changeSetManager->getScheduledInsertions();
        
        self::assertCount(2, $insertions);
        self::assertContains($user, $insertions);
        self::assertContains($unit, $insertions);
    }

    public function testScheduleOperationsWithDependencies(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        
        $this->changeSetManager->scheduleInsert($unit);
        $this->changeSetManager->scheduleInsert($user);
        
        $insertions = $this->changeSetManager->getScheduledInsertions();
        
        self::assertContains($unit, $insertions);
        self::assertContains($user, $insertions);
    }
}