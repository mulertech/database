<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\EntityStateManager;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class EntityStateManagerTest extends TestCase
{
    private EntityStateManager $stateManager;
    private IdentityMap $identityMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identityMap = new IdentityMap(new MetadataCache());
        $this->stateManager = new EntityStateManager($this->identityMap);
    }

    public function testTransitionToNew(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->stateManager->transitionToNew($user);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::NEW, $metadata->state);
    }

    public function testTransitionToManaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->stateManager->transitionToManaged($user);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata->state);
    }

    public function testTransitionToRemoved(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        $this->stateManager->transitionToRemoved($user);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::REMOVED, $metadata->state);
    }

    public function testTransitionToDetached(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        $this->stateManager->transitionToDetached($user);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::DETACHED, $metadata->state);
    }

    public function testGetCurrentState(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        $state = $this->stateManager->getCurrentState($user);
        
        self::assertEquals(EntityLifecycleState::MANAGED, $state);
    }

    public function testGetCurrentStateUnmanaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $state = $this->stateManager->getCurrentState($user);
        
        self::assertNull($state);
    }

    public function testIsInState(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        self::assertTrue($this->stateManager->isInState($user, EntityLifecycleState::MANAGED));
        self::assertFalse($this->stateManager->isInState($user, EntityLifecycleState::NEW));
        self::assertFalse($this->stateManager->isInState($user, EntityLifecycleState::REMOVED));
        self::assertFalse($this->stateManager->isInState($user, EntityLifecycleState::DETACHED));
    }

    public function testIsInStateUnmanaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        self::assertFalse($this->stateManager->isInState($user, EntityLifecycleState::MANAGED));
        self::assertFalse($this->stateManager->isInState($user, EntityLifecycleState::NEW));
        self::assertFalse($this->stateManager->isInState($user, EntityLifecycleState::REMOVED));
        self::assertFalse($this->stateManager->isInState($user, EntityLifecycleState::DETACHED));
    }

    public function testCanTransitionTo(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->identityMap->add($user);

        self::assertTrue($this->stateManager->canTransitionTo($user, EntityLifecycleState::MANAGED));
        self::assertTrue($this->stateManager->canTransitionTo($user, EntityLifecycleState::DETACHED));
        self::assertFalse($this->stateManager->canTransitionTo($user, EntityLifecycleState::REMOVED));
    }

    public function testCanTransitionToFromManaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        self::assertTrue($this->stateManager->canTransitionTo($user, EntityLifecycleState::REMOVED));
        self::assertTrue($this->stateManager->canTransitionTo($user, EntityLifecycleState::DETACHED));
        self::assertFalse($this->stateManager->canTransitionTo($user, EntityLifecycleState::NEW));
    }

    public function testCanTransitionToUnmanaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        self::assertTrue($this->stateManager->canTransitionTo($user, EntityLifecycleState::NEW));
        self::assertTrue($this->stateManager->canTransitionTo($user, EntityLifecycleState::MANAGED));
        self::assertFalse($this->stateManager->canTransitionTo($user, EntityLifecycleState::REMOVED));
        self::assertFalse($this->stateManager->canTransitionTo($user, EntityLifecycleState::DETACHED));
    }

    public function testUpdateOriginalData(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        $newData = ['username' => 'Updated'];
        $this->stateManager->updateOriginalData($user, $newData);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals($newData, $metadata->originalData);
    }

    public function testUpdateOriginalDataUnmanaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $newData = ['username' => 'Updated'];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity is not managed');
        
        $this->stateManager->updateOriginalData($user, $newData);
    }

    public function testGetOriginalData(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        $result = $this->stateManager->getOriginalData($user);
        
        self::assertIsArray($result);
        self::assertEquals('John', $result['username']);
    }

    public function testGetOriginalDataUnmanaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $result = $this->stateManager->getOriginalData($user);
        
        self::assertNull($result);
    }

    public function testMarkAsPersisted(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->identityMap->add($user);

        $this->stateManager->markAsPersisted($user, 123);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata->state);
        
        $retrievedUser = $this->identityMap->get(User::class, 123);
        self::assertSame($user, $retrievedUser);
    }

    public function testMarkAsPersistedAlreadyManaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity is already persisted');
        
        $this->stateManager->markAsPersisted($user, 123);
    }

    public function testMultipleEntityStates(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user2 = new User();
        $user2->setUsername('Jane');
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $this->stateManager->transitionToNew($user1);
        $this->stateManager->transitionToManaged($user2);
        $this->stateManager->transitionToNew($unit);
        
        self::assertTrue($this->stateManager->isInState($user1, EntityLifecycleState::NEW));
        self::assertTrue($this->stateManager->isInState($user2, EntityLifecycleState::MANAGED));
        self::assertTrue($this->stateManager->isInState($unit, EntityLifecycleState::NEW));
    }

    public function testMarkAsPersistedUnmanagedEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        // Entity is not added to identity map, so metadata will be null
        // This should trigger the echo statement and throw exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity is not managed');
        
        $this->stateManager->markAsPersisted($user, 123);
    }
}

