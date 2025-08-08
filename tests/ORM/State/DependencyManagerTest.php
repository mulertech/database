<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use MulerTech\Database\ORM\State\DependencyManager;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class DependencyManagerTest extends TestCase
{
    private DependencyManager $dependencyManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dependencyManager = new DependencyManager();
    }

    public function testAddInsertionDependency(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->addInsertionDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getInsertionDependencies($user);
        
        self::assertContains($unit, $dependencies);
    }

    public function testAddUpdateDependency(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->addUpdateDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getUpdateDependencies($user);
        
        self::assertContains($unit, $dependencies);
    }

    public function testAddDeletionDependency(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->addDeletionDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getDeletionDependencies($user);
        
        self::assertContains($unit, $dependencies);
    }

    public function testGetInsertionDependenciesEmpty(): void
    {
        $user = new User();
        
        $dependencies = $this->dependencyManager->getInsertionDependencies($user);
        
        self::assertEmpty($dependencies);
    }

    public function testGetUpdateDependenciesEmpty(): void
    {
        $user = new User();
        
        $dependencies = $this->dependencyManager->getUpdateDependencies($user);
        
        self::assertEmpty($dependencies);
    }

    public function testGetDeletionDependenciesEmpty(): void
    {
        $user = new User();
        
        $dependencies = $this->dependencyManager->getDeletionDependencies($user);
        
        self::assertEmpty($dependencies);
    }

    public function testMultipleDependencies(): void
    {
        $user = new User();
        $unit1 = new Unit();
        $unit2 = new Unit();
        
        $this->dependencyManager->addInsertionDependency($user, $unit1);
        $this->dependencyManager->addInsertionDependency($user, $unit2);
        
        $dependencies = $this->dependencyManager->getInsertionDependencies($user);
        
        self::assertCount(2, $dependencies);
        self::assertContains($unit1, $dependencies);
        self::assertContains($unit2, $dependencies);
    }

    public function testNoDuplicateDependencies(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->addInsertionDependency($user, $unit);
        $this->dependencyManager->addInsertionDependency($user, $unit);
        $this->dependencyManager->addInsertionDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getInsertionDependencies($user);
        
        self::assertCount(1, $dependencies);
        self::assertContains($unit, $dependencies);
    }

    public function testRemoveInsertionDependency(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->addInsertionDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getInsertionDependencies($user);
        self::assertContains($unit, $dependencies);
        
        $this->dependencyManager->removeInsertionDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getInsertionDependencies($user);
        self::assertNotContains($unit, $dependencies);
    }

    public function testRemoveUpdateDependency(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->addUpdateDependency($user, $unit);
        $this->dependencyManager->removeUpdateDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getUpdateDependencies($user);
        
        self::assertNotContains($unit, $dependencies);
    }

    public function testRemoveDeletionDependency(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->addDeletionDependency($user, $unit);
        $this->dependencyManager->removeDeletionDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getDeletionDependencies($user);
        
        self::assertNotContains($unit, $dependencies);
    }

    public function testRemoveNonExistentDependency(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->removeInsertionDependency($user, $unit);
        
        $dependencies = $this->dependencyManager->getInsertionDependencies($user);
        
        self::assertEmpty($dependencies);
    }

    public function testOrderByDependenciesSimple(): void
    {
        $unit = new Unit();
        $user = new User();
        
        $this->dependencyManager->addInsertionDependency($user, $unit);
        
        $entities = [
            spl_object_id($user) => $user,
            spl_object_id($unit) => $unit,
        ];
        
        $ordered = $this->dependencyManager->orderByDependencies($entities);
        
        $orderedIds = array_keys($ordered);
        $unitPosition = array_search(spl_object_id($unit), $orderedIds, true);
        $userPosition = array_search(spl_object_id($user), $orderedIds, true);
        
        self::assertLessThan($userPosition, $unitPosition);
    }

    public function testOrderByDependenciesComplex(): void
    {
        $unit1 = new Unit();
        $unit2 = new Unit();
        $user1 = new User();
        $user2 = new User();
        
        $this->dependencyManager->addInsertionDependency($user1, $unit1);
        $this->dependencyManager->addInsertionDependency($user2, $unit2);
        $this->dependencyManager->addInsertionDependency($user2, $user1);
        
        $entities = [
            spl_object_id($user2) => $user2,
            spl_object_id($user1) => $user1,
            spl_object_id($unit2) => $unit2,
            spl_object_id($unit1) => $unit1,
        ];
        
        $ordered = $this->dependencyManager->orderByDependencies($entities);
        
        $orderedIds = array_keys($ordered);
        
        $unit1Position = array_search(spl_object_id($unit1), $orderedIds, true);
        $unit2Position = array_search(spl_object_id($unit2), $orderedIds, true);
        $user1Position = array_search(spl_object_id($user1), $orderedIds, true);
        $user2Position = array_search(spl_object_id($user2), $orderedIds, true);
        
        self::assertLessThan($user1Position, $unit1Position);
        self::assertLessThan($user2Position, $unit2Position);
        self::assertLessThan($user2Position, $user1Position);
    }

    public function testOrderByDependenciesNoDependencies(): void
    {
        $unit = new Unit();
        $user = new User();
        
        $entities = [
            spl_object_id($user) => $user,
            spl_object_id($unit) => $unit,
        ];
        
        $ordered = $this->dependencyManager->orderByDependencies($entities);
        
        self::assertCount(2, $ordered);
        self::assertArrayHasKey(spl_object_id($user), $ordered);
        self::assertArrayHasKey(spl_object_id($unit), $ordered);
    }

    public function testClearDependencies(): void
    {
        $user = new User();
        $unit = new Unit();
        
        $this->dependencyManager->addInsertionDependency($user, $unit);
        $this->dependencyManager->addUpdateDependency($user, $unit);
        $this->dependencyManager->addDeletionDependency($user, $unit);
        
        self::assertNotEmpty($this->dependencyManager->getInsertionDependencies($user));
        self::assertNotEmpty($this->dependencyManager->getUpdateDependencies($user));
        self::assertNotEmpty($this->dependencyManager->getDeletionDependencies($user));
        
        $this->dependencyManager->clearDependencies($user);
        
        self::assertEmpty($this->dependencyManager->getInsertionDependencies($user));
        self::assertEmpty($this->dependencyManager->getUpdateDependencies($user));
        self::assertEmpty($this->dependencyManager->getDeletionDependencies($user));
    }

    public function testHasDependencies(): void
    {
        $user = new User();
        $unit = new Unit();
        
        self::assertFalse($this->dependencyManager->hasDependencies($user));
        
        $this->dependencyManager->addInsertionDependency($user, $unit);
        
        self::assertTrue($this->dependencyManager->hasDependencies($user));
        
        $this->dependencyManager->clearDependencies($user);
        
        self::assertFalse($this->dependencyManager->hasDependencies($user));
    }

    public function testCircularDependencyDetection(): void
    {
        $user1 = new User();
        $user2 = new User();
        
        $this->dependencyManager->addInsertionDependency($user1, $user2);
        $this->dependencyManager->addInsertionDependency($user2, $user1);
        
        $entities = [
            spl_object_id($user1) => $user1,
            spl_object_id($user2) => $user2,
        ];
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');
        
        $this->dependencyManager->orderByDependencies($entities);
    }

    public function testVisitEntityAlreadyVisitedCase(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        // Create a dependency chain where entity can be visited multiple times
        $this->dependencyManager->addInsertionDependency($user1, $unit);
        $this->dependencyManager->addInsertionDependency($user2, $unit);
        
        $entities = [
            spl_object_id($user1) => $user1,
            spl_object_id($user2) => $user2,
            spl_object_id($unit) => $unit,
        ];
        
        // This should trigger the echo when visiting the unit the second time
        $ordered = $this->dependencyManager->orderByDependencies($entities);
        
        // Verify proper ordering - unit should come before both users
        $orderedIds = array_keys($ordered);
        $unitPosition = array_search(spl_object_id($unit), $orderedIds, true);
        $user1Position = array_search(spl_object_id($user1), $orderedIds, true);
        $user2Position = array_search(spl_object_id($user2), $orderedIds, true);
        
        self::assertLessThan($user1Position, $unitPosition);
        self::assertLessThan($user2Position, $unitPosition);
        self::assertCount(3, $ordered);
    }
}