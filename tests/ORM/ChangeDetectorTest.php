<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\PropertyChange;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class ChangeDetectorTest extends TestCase
{
    private ChangeDetector $changeDetector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->changeDetector = new ChangeDetector();
    }

    public function testExtractCurrentDataWithBasicProperties(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setAge(25);
        
        $data = $this->changeDetector->extractCurrentData($user);
        
        self::assertIsArray($data);
        self::assertArrayHasKey('username', $data);
        self::assertArrayHasKey('age', $data);
        self::assertEquals('John', $data['username']);
        self::assertEquals(25, $data['age']);
    }

    public function testExtractCurrentDataWithNullValues(): void
    {
        $user = new User();
        
        $data = $this->changeDetector->extractCurrentData($user);
        
        self::assertIsArray($data);
        self::assertArrayHasKey('username', $data);
        self::assertNull($data['username']);
    }

    public function testExtractCurrentDataWithRelations(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        
        $data = $this->changeDetector->extractCurrentData($user);
        
        self::assertIsArray($data);
        self::assertArrayHasKey('unit', $data);
        self::assertNotNull($data['unit']);
    }

    public function testComputeChangeSetWithNoChanges(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $originalData = $this->changeDetector->extractCurrentData($user);
        
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        self::assertInstanceOf(ChangeSet::class, $changeSet);
        self::assertTrue($changeSet->isEmpty());
        self::assertEquals(User::class, $changeSet->entityClass);
    }

    public function testComputeChangeSetWithChanges(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setAge(25);
        
        $originalData = $this->changeDetector->extractCurrentData($user);
        
        $user->setUsername('Jane');
        $user->setAge(30);
        
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        self::assertInstanceOf(ChangeSet::class, $changeSet);
        self::assertFalse($changeSet->isEmpty());
        self::assertCount(2, $changeSet->getChanges());
        
        $usernameChange = $changeSet->getFieldChange('username');
        self::assertInstanceOf(PropertyChange::class, $usernameChange);
        self::assertEquals('username', $usernameChange->property);
        self::assertEquals('John', $usernameChange->oldValue);
        self::assertEquals('Jane', $usernameChange->newValue);
        
        $ageChange = $changeSet->getFieldChange('age');
        self::assertInstanceOf(PropertyChange::class, $ageChange);
        self::assertEquals('age', $ageChange->property);
        self::assertEquals(25, $ageChange->oldValue);
        self::assertEquals(30, $ageChange->newValue);
    }

    public function testComputeChangeSetWithNullToValue(): void
    {
        $user = new User();
        $originalData = $this->changeDetector->extractCurrentData($user);
        
        $user->setUsername('John');
        
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        self::assertFalse($changeSet->isEmpty());
        
        $usernameChange = $changeSet->getFieldChange('username');
        self::assertInstanceOf(PropertyChange::class, $usernameChange);
        self::assertNull($usernameChange->oldValue);
        self::assertEquals('John', $usernameChange->newValue);
    }

    public function testComputeChangeSetWithValueToNull(): void
    {
        $user = new User();
        $user->setUsername('John');
        $originalData = $this->changeDetector->extractCurrentData($user);
        
        $user->setUsername('');
        
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        self::assertFalse($changeSet->isEmpty());
        
        $usernameChange = $changeSet->getFieldChange('username');
        self::assertInstanceOf(PropertyChange::class, $usernameChange);
        self::assertEquals('John', $usernameChange->oldValue);
        self::assertEquals('', $usernameChange->newValue);
    }

    public function testComputeChangeSetWithRelationChanges(): void
    {
        $unit1 = new Unit();
        $unit1->setName('Unit1');
        
        $unit2 = new Unit();
        $unit2->setName('Unit2');
        
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit1);
        
        $originalData = $this->changeDetector->extractCurrentData($user);
        
        $user->setUnit($unit2);
        
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        self::assertFalse($changeSet->isEmpty());
        
        $unitChange = $changeSet->getFieldChange('unit');
        self::assertInstanceOf(PropertyChange::class, $unitChange);
        self::assertEquals('unit', $unitChange->property);
        self::assertNotEquals($unitChange->oldValue, $unitChange->newValue);
    }

    public function testComputeChangeSetIgnoresStaticProperties(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $data = $this->changeDetector->extractCurrentData($user);
        
        foreach (array_keys($data) as $property) {
            $reflection = new \ReflectionProperty(User::class, $property);
            self::assertFalse($reflection->isStatic());
        }
    }

    public function testComputeChangeSetWithMixedChanges(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setAge(25);
        
        $originalData = $this->changeDetector->extractCurrentData($user);
        
        $user->setUsername('Jane');
        
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        self::assertFalse($changeSet->isEmpty());
        self::assertCount(1, $changeSet->getChanges());
        
        $usernameChange = $changeSet->getFieldChange('username');
        self::assertInstanceOf(PropertyChange::class, $usernameChange);
        
        $ageChange = $changeSet->getFieldChange('age');
        self::assertNull($ageChange);
    }
}