<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\PropertyChange;
use MulerTech\Database\Mapping\Attributes\MtEntity;
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

    public function testComputeChangeSetWithRemovedProperty(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $originalData = ['username' => 'John', 'removedProperty' => 'value'];
        
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        self::assertFalse($changeSet->isEmpty());
        
        $removedPropertyChange = $changeSet->getFieldChange('removedProperty');
        self::assertInstanceOf(PropertyChange::class, $removedPropertyChange);
        self::assertEquals('removedProperty', $removedPropertyChange->property);
        self::assertEquals('value', $removedPropertyChange->oldValue);
        self::assertNull($removedPropertyChange->newValue);
    }

    public function testComputeChangeSetWithSimilarObjects(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        // Test the default comparison case with similar objects that compare as equal
        $originalData = $this->changeDetector->extractCurrentData($user);
        
        // No changes - both old and new are the same
        $changeSet = $this->changeDetector->computeChangeSet($user, $originalData);
        
        self::assertTrue($changeSet->isEmpty());
    }

    public function testExtractCurrentDataHandlesUninitializedProperties(): void
    {
        $user = new User();
        // Don't set any properties, so some remain uninitialized
        
        $data = $this->changeDetector->extractCurrentData($user);
        
        self::assertIsArray($data);
        // All uninitialized properties should be null in the data
        self::assertNull($data['username'] ?? null);
        self::assertNull($data['age'] ?? null);
    }

    public function testExtractCurrentDataWithTrulyUninitializedProperty(): void
    {
        // Create a simple test class with uninitialized properties (no default values)
        $testEntity = new #[MtEntity(tableName: 'test_table')] class {
            private string $uninitializedString;
            private int $uninitializedInt;
            private ?string $initializedString = 'test';
            
            public function getUninitializedString(): ?string {
                return isset($this->uninitializedString) ? $this->uninitializedString : null;
            }
            
            public function getUninitializedInt(): ?int {
                return isset($this->uninitializedInt) ? $this->uninitializedInt : null;
            }
            
            public function getInitializedString(): ?string {
                return $this->initializedString;
            }
        };
        
        // This should trigger the echo statement at line 70
        $data = $this->changeDetector->extractCurrentData($testEntity);
        
        self::assertIsArray($data);
        // Uninitialized properties should be set to null
        self::assertNull($data['uninitializedString']);
        self::assertNull($data['uninitializedInt']);
        // Initialized property should have its value
        self::assertEquals('test', $data['initializedString']);
    }

    public function testValuesAreEqualWithObjectType(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user1->setAge(25);
        
        $user2 = new User();
        $user2->setUsername('John');
        $user2->setAge(30); // Different age to ensure a change
        
        $originalData = $this->changeDetector->extractCurrentData($user1);
        
        // Modify user2 to trigger the comparison with object types
        $changeSet = $this->changeDetector->computeChangeSet($user2, $originalData);
        
        // This should have triggered the echo statement for object type comparison
        self::assertFalse($changeSet->isEmpty());
    }

    public function testValuesAreEqualWithActualObjectType(): void
    {
        // Create a test entity with stdClass properties to force object type comparison
        $entity1 = new #[MtEntity(tableName: 'test_entity1')] class {
            public \stdClass $objectProperty;
            
            public function __construct() {
                $this->objectProperty = new \stdClass();
                $this->objectProperty->data = 'test1';
            }
            
            public function getObjectProperty(): \stdClass {
                return $this->objectProperty;
            }
        };
        
        $entity2 = new #[MtEntity(tableName: 'test_entity2')] class {
            public \stdClass $objectProperty;
            
            public function __construct() {
                $this->objectProperty = new \stdClass();
                $this->objectProperty->data = 'test2';
            }
            
            public function getObjectProperty(): \stdClass {
                return $this->objectProperty;
            }
        };
        
        $originalData = $this->changeDetector->extractCurrentData($entity1);
        
        // This should trigger the valuesAreEqual method with actual 'object' types
        // and should trigger both var_dump and echo statements
        $changeSet = $this->changeDetector->computeChangeSet($entity2, $originalData);
        
        // The change set should reflect the difference in object properties
        self::assertFalse($changeSet->isEmpty());
    }
}