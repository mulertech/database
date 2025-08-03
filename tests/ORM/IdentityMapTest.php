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
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        $entityState2 = new EntityState(
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
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        $unitState = new EntityState(
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
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        $managedState = new EntityState(
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
            EntityLifecycleState::NEW,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $originalState);
        
        $newState = new EntityState(
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
}