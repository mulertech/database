<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\ORM\EntityRegistry;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class EntityRegistryTest extends TestCase
{
    private EntityRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new EntityRegistry();
    }

    public function testRegisterAndIsRegistered(): void
    {
        $user = new User();
        
        self::assertFalse($this->registry->isRegistered($user));
        
        $this->registry->register($user);
        
        self::assertTrue($this->registry->isRegistered($user));
    }

    public function testUnregister(): void
    {
        $user = new User();
        
        $this->registry->register($user);
        self::assertTrue($this->registry->isRegistered($user));
        
        $this->registry->unregister($user);
        
        self::assertFalse($this->registry->isRegistered($user));
    }

    public function testGetRegisteredEntities(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $entities = $this->registry->getRegisteredEntities();
        self::assertEmpty($entities);
        
        $this->registry->register($user1);
        $this->registry->register($user2);
        $this->registry->register($unit);
        
        $entities = $this->registry->getRegisteredEntities();
        
        self::assertCount(3, $entities);
        self::assertContains($user1, $entities);
        self::assertContains($user2, $entities);
        self::assertContains($unit, $entities);
    }

    public function testGetRegisteredEntitiesByClass(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $this->registry->register($user1);
        $this->registry->register($user2);
        $this->registry->register($unit);
        
        $users = $this->registry->getRegisteredEntitiesByClass(User::class);
        $units = $this->registry->getRegisteredEntitiesByClass(Unit::class);
        
        self::assertCount(2, $users);
        self::assertCount(1, $units);
        self::assertContains($user1, $users);
        self::assertContains($user2, $users);
        self::assertContains($unit, $units);
    }

    public function testGetRegisteredEntitiesByClassWithNonExistentClass(): void
    {
        $user = new User();
        $this->registry->register($user);
        
        $entities = $this->registry->getRegisteredEntitiesByClass('NonExistentClass');
        
        self::assertEmpty($entities);
    }

    public function testClear(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $this->registry->register($user1);
        $this->registry->register($user2);
        $this->registry->register($unit);
        
        self::assertTrue($this->registry->isRegistered($user1));
        self::assertTrue($this->registry->isRegistered($user2));
        self::assertTrue($this->registry->isRegistered($unit));
        
        $this->registry->clear();
        
        self::assertFalse($this->registry->isRegistered($user1));
        self::assertFalse($this->registry->isRegistered($user2));
        self::assertFalse($this->registry->isRegistered($unit));
        self::assertEmpty($this->registry->getRegisteredEntities());
    }

    public function testRegisterSameEntityMultipleTimes(): void
    {
        $user = new User();
        
        $this->registry->register($user);
        $this->registry->register($user);
        $this->registry->register($user);
        
        self::assertTrue($this->registry->isRegistered($user));
        
        $entities = $this->registry->getRegisteredEntities();
        self::assertCount(1, $entities);
    }

    public function testUnregisterNonRegisteredEntity(): void
    {
        $user = new User();
        
        self::assertFalse($this->registry->isRegistered($user));
        
        $this->registry->unregister($user);
        
        self::assertFalse($this->registry->isRegistered($user));
    }

    public function testWeakReference(): void
    {
        $user = new User();
        $this->registry->register($user);
        
        self::assertTrue($this->registry->isRegistered($user));
        
        unset($user);
        
        gc_collect_cycles();
        
        $entities = $this->registry->getRegisteredEntities();
        self::assertEmpty($entities);
    }

    public function testEntitiesCanBeGarbageCollected(): void
    {
        $user1 = new User();
        $user2 = new User();
        
        $this->registry->register($user1);
        $this->registry->register($user2);
        
        self::assertCount(2, $this->registry->getRegisteredEntities());
        
        unset($user1);
        gc_collect_cycles();
        
        $remainingEntities = $this->registry->getRegisteredEntities();
        self::assertCount(1, $remainingEntities);
        self::assertContains($user2, $remainingEntities);
    }

    public function testMixedEntityTypes(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $this->registry->register($user);
        $this->registry->register($unit);
        
        self::assertTrue($this->registry->isRegistered($user));
        self::assertTrue($this->registry->isRegistered($unit));
        
        $allEntities = $this->registry->getRegisteredEntities();
        self::assertCount(2, $allEntities);
        
        $users = $this->registry->getRegisteredEntitiesByClass(User::class);
        $units = $this->registry->getRegisteredEntitiesByClass(Unit::class);
        
        self::assertCount(1, $users);
        self::assertCount(1, $units);
        self::assertSame($user, $users[0]);
        self::assertSame($unit, $units[0]);
    }
}