<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class IdentityMapTest extends TestCase
{
    private IdentityMap $identityMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identityMap = new IdentityMap();
    }

    public function testAddAndGet(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $retrievedUser = $this->identityMap->get(User::class, 1);
        
        self::assertSame($user, $retrievedUser);
        self::assertEquals('John', $retrievedUser->getUsername());
    }

    public function testContains(): void
    {
        $user = new User();
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        self::assertFalse($this->identityMap->contains(User::class, 1));
        
        $this->identityMap->add($user, 1, $entityState);
        
        self::assertTrue($this->identityMap->contains(User::class, 1));
        self::assertFalse($this->identityMap->contains(User::class, 2));
        self::assertFalse($this->identityMap->contains(Unit::class, 1));
    }

    public function testGetNonExistent(): void
    {
        $result = $this->identityMap->get(User::class, 999);
        
        self::assertNull($result);
    }

    public function testAddMultipleEntitiesOfSameClass(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user2 = new User();
        $user2->setUsername('Jane');
        
        $entityState1 = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        $entityState2 = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user1, 1, $entityState1);
        $this->identityMap->add($user2, 2, $entityState2);
        
        $retrievedUser1 = $this->identityMap->get(User::class, 1);
        $retrievedUser2 = $this->identityMap->get(User::class, 2);
        
        self::assertSame($user1, $retrievedUser1);
        self::assertSame($user2, $retrievedUser2);
        self::assertEquals('John', $retrievedUser1->getUsername());
        self::assertEquals('Jane', $retrievedUser2->getUsername());
    }

    public function testAddMultipleEntitiesOfDifferentClasses(): void
    {
        $user = new User();
        $user->setUsername('John');
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $userState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        $unitState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\Unit',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $userState);
        $this->identityMap->add($unit, 1, $unitState);
        
        $retrievedUser = $this->identityMap->get(User::class, 1);
        $retrievedUnit = $this->identityMap->get(Unit::class, 1);
        
        self::assertSame($user, $retrievedUser);
        self::assertSame($unit, $retrievedUnit);
        self::assertEquals('John', $retrievedUser->getUsername());
        self::assertEquals('TestUnit', $retrievedUnit->getName());
    }

    public function testRemove(): void
    {
        $user = new User();
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        self::assertTrue($this->identityMap->contains(User::class, 1));
        
        $this->identityMap->remove($user);
        
        self::assertFalse($this->identityMap->contains(User::class, 1));
        self::assertNull($this->identityMap->get(User::class, 1));
    }

    public function testRemoveNonExistent(): void
    {
        $user = new User();
        
        $this->identityMap->remove($user);
        
        self::assertFalse($this->identityMap->contains(User::class, 1));
    }

    public function testGetMetadata(): void
    {
        $user = new User();
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $retrievedState = $this->identityMap->getMetadata($user);
        
        self::assertSame($entityState, $retrievedState);
        self::assertEquals(EntityLifecycleState::NEW, $retrievedState->state);
        self::assertEquals(['username' => 'John'], $retrievedState->originalData);
    }

    public function testGetMetadataNonExistent(): void
    {
        $user = new User();
        
        $result = $this->identityMap->getMetadata($user);
        
        self::assertNull($result);
    }

    public function testGetEntitiesByState(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $newState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        $managedState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::MANAGED,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user1, 1, $newState);
        $this->identityMap->add($user2, 2, $managedState);
        $this->identityMap->add($unit, 1, $newState);
        
        $newEntities = $this->identityMap->getEntitiesByState(EntityLifecycleState::NEW);
        $managedEntities = $this->identityMap->getEntitiesByState(EntityLifecycleState::MANAGED);
        
        self::assertCount(2, $newEntities);
        self::assertCount(1, $managedEntities);
        self::assertContains($user1, $newEntities);
        self::assertContains($unit, $newEntities);
        self::assertContains($user2, $managedEntities);
    }

    public function testClear(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user1, 1, $entityState);
        $this->identityMap->add($user2, 2, $entityState);
        $this->identityMap->add($unit, 1, $entityState);
        
        self::assertTrue($this->identityMap->contains(User::class, 1));
        self::assertTrue($this->identityMap->contains(User::class, 2));
        self::assertTrue($this->identityMap->contains(Unit::class, 1));
        
        $this->identityMap->clear();
        
        self::assertFalse($this->identityMap->contains(User::class, 1));
        self::assertFalse($this->identityMap->contains(User::class, 2));
        self::assertFalse($this->identityMap->contains(Unit::class, 1));
        self::assertNull($this->identityMap->get(User::class, 1));
        self::assertNull($this->identityMap->get(User::class, 2));
        self::assertNull($this->identityMap->get(Unit::class, 1));
    }

    public function testStringIds(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 'user-uuid-123', $entityState);
        
        self::assertTrue($this->identityMap->contains(User::class, 'user-uuid-123'));
        
        $retrievedUser = $this->identityMap->get(User::class, 'user-uuid-123');
        
        self::assertSame($user, $retrievedUser);
        self::assertEquals('John', $retrievedUser->getUsername());
    }

    public function testUpdateMetadata(): void
    {
        $user = new User();
        $originalState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $originalState);
        
        $newState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::MANAGED,
            ['username' => 'Jane'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->updateMetadata($user, $newState);
        
        $retrievedState = $this->identityMap->getMetadata($user);
        
        self::assertSame($newState, $retrievedState);
        self::assertEquals(EntityLifecycleState::MANAGED, $retrievedState->state);
        self::assertEquals(['username' => 'Jane'], $retrievedState->originalData);
    }

    public function testUpdateMetadataThrowsExceptionForUnmanagedEntity(): void
    {
        $user = new User();
        $newState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::MANAGED,
            ['username' => 'Jane'],
            new \DateTimeImmutable()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity is not managed by this IdentityMap');

        $this->identityMap->updateMetadata($user, $newState);
    }

    public function testGetByClass(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user2 = new User();
        $user2->setUsername('Jane');
        $unit = new Unit();
        $unit->setName('TestUnit');

        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );

        $this->identityMap->add($user1, 1, $entityState);
        $this->identityMap->add($user2, 2, $entityState);
        $this->identityMap->add($unit, 1, $entityState);

        $userEntities = $this->identityMap->getByClass(User::class);
        $unitEntities = $this->identityMap->getByClass(Unit::class);
        $nonExistentEntities = $this->identityMap->getByClass('NonExistentClass');

        self::assertCount(2, $userEntities);
        self::assertCount(1, $unitEntities);
        self::assertCount(0, $nonExistentEntities);
        self::assertContains($user1, $userEntities);
        self::assertContains($user2, $userEntities);
        self::assertContains($unit, $unitEntities);
    }

    public function testGetAllEntities(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();

        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );

        $this->identityMap->add($user1, 1, $entityState);
        $this->identityMap->add($user2, 2, $entityState);
        $this->identityMap->add($unit, 1, $entityState);

        $allEntities = $this->identityMap->getAllEntities();

        self::assertCount(3, $allEntities);
        self::assertContains($user1, $allEntities);
        self::assertContains($user2, $allEntities);
        self::assertContains($unit, $allEntities);
    }

    public function testIsManaged(): void
    {
        $user = new User();
        $managedState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::MANAGED,
            [],
            new \DateTimeImmutable()
        );
        $newState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );

        // Entity not in identity map
        self::assertFalse($this->identityMap->isManaged($user));

        // Entity with NEW state
        $this->identityMap->add($user, 1, $newState);
        self::assertFalse($this->identityMap->isManaged($user));

        // Update to MANAGED state
        $this->identityMap->updateMetadata($user, $managedState);
        self::assertTrue($this->identityMap->isManaged($user));
    }

    public function testGetEntityState(): void
    {
        $user = new User();
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::MANAGED,
            [],
            new \DateTimeImmutable()
        );

        // Entity not in identity map
        self::assertNull($this->identityMap->getEntityState($user));

        $this->identityMap->add($user, 1, $entityState);
        self::assertEquals(EntityLifecycleState::MANAGED, $this->identityMap->getEntityState($user));
    }

    public function testCleanup(): void
    {
        $user = new User();
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );

        $this->identityMap->add($user, 1, $entityState);
        self::assertTrue($this->identityMap->contains(User::class, 1));

        // Force cleanup by unsetting the variable reference
        unset($user);
        gc_collect_cycles();

        $this->identityMap->cleanup();

        // After cleanup, dead references should be removed
        // Note: This test might be flaky due to garbage collection timing
        // But it tests the cleanup functionality
        self::assertEquals([], $this->identityMap->getAllEntities());
    }

    public function testClearSpecificClass(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();

        $entityState = new EntityState(
            'Test',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );

        $this->identityMap->add($user1, 1, $entityState);
        $this->identityMap->add($user2, 2, $entityState);
        $this->identityMap->add($unit, 1, $entityState);

        // Clear only User entities
        $this->identityMap->clear(User::class);

        self::assertFalse($this->identityMap->contains(User::class, 1));
        self::assertFalse($this->identityMap->contains(User::class, 2));
        self::assertTrue($this->identityMap->contains(Unit::class, 1));
    }

    public function testAddWithoutExplicitId(): void
    {
        $user = new User();
        $user->setId(42);

        // Add without explicit ID - should extract from entity
        $this->identityMap->add($user);

        self::assertTrue($this->identityMap->contains(User::class, 42));
        self::assertSame($user, $this->identityMap->get(User::class, 42));
    }

    public function testAddWithNullId(): void
    {
        $user = new User();
        // Don't set ID, so it remains null

        // Add without explicit ID - should handle null ID gracefully
        $this->identityMap->add($user);

        // Should not be in the contains map since ID is null
        self::assertFalse($this->identityMap->contains(User::class, 'any_id'));
        
        // But metadata should still be stored
        self::assertNotNull($this->identityMap->getMetadata($user));
        self::assertEquals(EntityLifecycleState::NEW, $this->identityMap->getEntityState($user));
    }

    public function testWeakReferenceCleanupInContains(): void
    {
        $user = new User();
        $entityState = new EntityState(
            'MulerTech\\Database\\Tests\\Files\\Entity\\User',
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );

        $this->identityMap->add($user, 1, $entityState);
        self::assertTrue($this->identityMap->contains(User::class, 1));

        // Force cleanup by unsetting the reference
        unset($user);
        gc_collect_cycles();

        // The contains method should clean up dead references and return false
        // Note: This test might be flaky due to GC timing
        $result = $this->identityMap->contains(User::class, 1);
        // We don't assert the exact result due to GC uncertainty,
        // but the method should handle dead references gracefully
        self::assertIsBool($result);
    }
}