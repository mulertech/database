<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\StateResolver;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

class StateResolverTest extends TestCase
{
    private StateResolver $stateResolver;
    private IdentityMap $identityMap;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->identityMap = new IdentityMap();
        
        $this->stateResolver = new StateResolver(
            $this->identityMap,
            null // No ChangeSetManager for simplicity
        );
    }

    public function testResolveEntityLifecycleStateFromIdentityMap(): void
    {
        $entity = new User();
        
        $this->identityMap->add($entity);
        
        $result = $this->stateResolver->resolveEntityLifecycleState($entity);
        
        // Entity added to IdentityMap will be in NEW state
        $this->assertSame(EntityLifecycleState::NEW, $result);
    }

    public function testResolveEntityLifecycleStateReturnsDetachedWhenNotFound(): void
    {
        $entity = new User();
        
        $result = $this->stateResolver->resolveEntityLifecycleState($entity);
        
        $this->assertSame(EntityLifecycleState::DETACHED, $result);
    }

    public function testResolveEntityLifecycleStateWithoutChangeSetManager(): void
    {
        $stateResolver = new StateResolver($this->identityMap, null);
        
        $entity = new User();
        
        $result = $stateResolver->resolveEntityLifecycleState($entity);
        
        $this->assertSame(EntityLifecycleState::DETACHED, $result);
    }

    public function testResolveEntityLifecycleStateWithoutChangeSetManagerButInIdentityMap(): void
    {
        $stateResolver = new StateResolver($this->identityMap, null);
        
        $entity = new User();
        
        $this->identityMap->add($entity);
        
        $result = $stateResolver->resolveEntityLifecycleState($entity);
        
        // Entity added to IdentityMap will be in NEW state
        $this->assertSame(EntityLifecycleState::NEW, $result);
    }

    public function testResolveWithEntityInIdentityMapTakesPrecedence(): void
    {
        $entity = new User();
        
        // Add entity to identity map
        $this->identityMap->add($entity);
        
        $result = $this->stateResolver->resolveEntityLifecycleState($entity);
        
        // Should return state from identity map (NEW when just added)
        $this->assertSame(EntityLifecycleState::NEW, $result);
    }

    public function testResolveWithMultipleEntitiesInIdentityMap(): void
    {
        $entity1 = new User();
        $entity2 = new User();
        $entity3 = new User();
        
        $this->identityMap->add($entity1);
        $this->identityMap->add($entity2);
        
        // Test entity1 (in identity map)
        $result1 = $this->stateResolver->resolveEntityLifecycleState($entity1);
        $this->assertSame(EntityLifecycleState::NEW, $result1);
        
        // Test entity2 (in identity map)
        $result2 = $this->stateResolver->resolveEntityLifecycleState($entity2);
        $this->assertSame(EntityLifecycleState::NEW, $result2);
        
        // Test entity3 (not in identity map)
        $result3 = $this->stateResolver->resolveEntityLifecycleState($entity3);
        $this->assertSame(EntityLifecycleState::DETACHED, $result3);
    }

    public function testResolveIsConsistentForSameEntity(): void
    {
        $entity = new User();
        
        $this->identityMap->add($entity);
        
        // Call multiple times and ensure consistency
        $result1 = $this->stateResolver->resolveEntityLifecycleState($entity);
        $result2 = $this->stateResolver->resolveEntityLifecycleState($entity);
        $result3 = $this->stateResolver->resolveEntityLifecycleState($entity);
        
        $this->assertSame(EntityLifecycleState::NEW, $result1);
        $this->assertSame(EntityLifecycleState::NEW, $result2);
        $this->assertSame(EntityLifecycleState::NEW, $result3);
        
        // All results should be identical
        $this->assertSame($result1, $result2);
        $this->assertSame($result2, $result3);
    }

    public function testResolveWithDifferentEntityStates(): void
    {
        $entity1 = new User(); // Will be DETACHED
        $entity2 = new User(); // Will be NEW when added
        
        $this->identityMap->add($entity2);
        
        $result1 = $this->stateResolver->resolveEntityLifecycleState($entity1);
        $result2 = $this->stateResolver->resolveEntityLifecycleState($entity2);
        
        $this->assertSame(EntityLifecycleState::DETACHED, $result1);
        $this->assertSame(EntityLifecycleState::NEW, $result2);
    }

    public function testResolveHandlesEntityLifecycleStates(): void
    {
        // Test with entities in different scenarios
        $detachedEntity = new User();
        $newEntity = new User();
        
        // Add one to identity map (becomes NEW)
        $this->identityMap->add($newEntity);
        
        $detachedResult = $this->stateResolver->resolveEntityLifecycleState($detachedEntity);
        $newResult = $this->stateResolver->resolveEntityLifecycleState($newEntity);
        
        $this->assertSame(EntityLifecycleState::DETACHED, $detachedResult);
        $this->assertSame(EntityLifecycleState::NEW, $newResult);
    }
}