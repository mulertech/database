<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\PropertyChange;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

class ChangeSetTest extends TestCase
{
    public function testConstructorAndBasicProperties(): void
    {
        $changes = [
            'username' => new PropertyChange('username', 'John', 'Jane'),
            'age' => new PropertyChange('age', 25, 30),
        ];
        
        $changeSet = new ChangeSet(User::class, $changes);
        
        self::assertEquals(User::class, $changeSet->entityClass);
        self::assertEquals($changes, $changeSet->changes);
        self::assertEquals($changes, $changeSet->getChanges());
    }

    public function testIsEmptyWithEmptyChanges(): void
    {
        $changeSet = new ChangeSet(User::class, []);
        
        self::assertTrue($changeSet->isEmpty());
    }

    public function testIsEmptyWithChanges(): void
    {
        $changes = [
            'username' => new PropertyChange('username', 'John', 'Jane'),
        ];
        
        $changeSet = new ChangeSet(User::class, $changes);
        
        self::assertFalse($changeSet->isEmpty());
    }

    public function testGetFieldChangeWithExistingField(): void
    {
        $usernameChange = new PropertyChange('username', 'John', 'Jane');
        $ageChange = new PropertyChange('age', 25, 30);
        
        $changes = [
            'username' => $usernameChange,
            'age' => $ageChange,
        ];
        
        $changeSet = new ChangeSet(User::class, $changes);
        
        self::assertSame($usernameChange, $changeSet->getFieldChange('username'));
        self::assertSame($ageChange, $changeSet->getFieldChange('age'));
    }

    public function testGetFieldChangeWithNonExistingField(): void
    {
        $changes = [
            'username' => new PropertyChange('username', 'John', 'Jane'),
        ];
        
        $changeSet = new ChangeSet(User::class, $changes);
        
        self::assertNull($changeSet->getFieldChange('nonexistent'));
        self::assertNull($changeSet->getFieldChange('age'));
    }

    public function testFilterWithCallback(): void
    {
        $usernameChange = new PropertyChange('username', 'John', 'Jane');
        $ageChange = new PropertyChange('age', null, 30);
        $sizeChange = new PropertyChange('size', 180, null);
        
        $changes = [
            'username' => $usernameChange,
            'age' => $ageChange,
            'size' => $sizeChange,
        ];
        
        $changeSet = new ChangeSet(User::class, $changes);
        
        $filteredChangeSet = $changeSet->filter(function (PropertyChange $change) {
            return $change->oldValue !== null;
        });
        
        self::assertInstanceOf(ChangeSet::class, $filteredChangeSet);
        self::assertEquals(User::class, $filteredChangeSet->entityClass);
        self::assertCount(2, $filteredChangeSet->getChanges());
        self::assertArrayHasKey('username', $filteredChangeSet->getChanges());
        self::assertArrayHasKey('size', $filteredChangeSet->getChanges());
        self::assertArrayNotHasKey('age', $filteredChangeSet->getChanges());
    }

    public function testFilterWithCallbackReturningEmpty(): void
    {
        $changes = [
            'username' => new PropertyChange('username', 'John', 'Jane'),
            'age' => new PropertyChange('age', 25, 30),
        ];
        
        $changeSet = new ChangeSet(User::class, $changes);
        
        $filteredChangeSet = $changeSet->filter(function (PropertyChange $change) {
            return false;
        });
        
        self::assertInstanceOf(ChangeSet::class, $filteredChangeSet);
        self::assertTrue($filteredChangeSet->isEmpty());
        self::assertCount(0, $filteredChangeSet->getChanges());
    }

    public function testFilterWithCallbackReturningAll(): void
    {
        $changes = [
            'username' => new PropertyChange('username', 'John', 'Jane'),
            'age' => new PropertyChange('age', 25, 30),
        ];
        
        $changeSet = new ChangeSet(User::class, $changes);
        
        $filteredChangeSet = $changeSet->filter(function (PropertyChange $change) {
            return true;
        });
        
        self::assertInstanceOf(ChangeSet::class, $filteredChangeSet);
        self::assertFalse($filteredChangeSet->isEmpty());
        self::assertCount(2, $filteredChangeSet->getChanges());
        self::assertEquals($changes, $filteredChangeSet->getChanges());
    }

    public function testFilterWithComplexCallback(): void
    {
        $changes = [
            'username' => new PropertyChange('username', 'John', 'Jane'),
            'age' => new PropertyChange('age', 25, 30),
            'score' => new PropertyChange('score', 100, 150),
            'size' => new PropertyChange('size', null, 180),
        ];
        
        $changeSet = new ChangeSet(User::class, $changes);
        
        $filteredChangeSet = $changeSet->filter(function (PropertyChange $change) {
            return is_numeric($change->newValue) && $change->newValue > 100;
        });
        
        self::assertInstanceOf(ChangeSet::class, $filteredChangeSet);
        self::assertCount(2, $filteredChangeSet->getChanges());
        self::assertArrayHasKey('score', $filteredChangeSet->getChanges());
        self::assertArrayHasKey('size', $filteredChangeSet->getChanges());
        self::assertArrayNotHasKey('username', $filteredChangeSet->getChanges());
        self::assertArrayNotHasKey('age', $filteredChangeSet->getChanges());
    }

    public function testReadonlyClass(): void
    {
        $changeSet = new ChangeSet(User::class, []);
        
        $reflection = new \ReflectionClass($changeSet);
        
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    public function testMultipleChangeSetInstances(): void
    {
        $changes1 = ['username' => new PropertyChange('username', 'John', 'Jane')];
        $changes2 = ['age' => new PropertyChange('age', 25, 30)];
        
        $changeSet1 = new ChangeSet(User::class, $changes1);
        $changeSet2 = new ChangeSet(User::class, $changes2);
        
        self::assertNotSame($changeSet1, $changeSet2);
        self::assertEquals($changes1, $changeSet1->getChanges());
        self::assertEquals($changes2, $changeSet2->getChanges());
        self::assertNotEquals($changeSet1->getChanges(), $changeSet2->getChanges());
    }
}