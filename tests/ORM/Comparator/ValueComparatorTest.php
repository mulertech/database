<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Comparator;

use MulerTech\Database\ORM\Comparator\ValueComparator;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class ValueComparatorTest extends TestCase
{
    private ValueComparator $comparator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comparator = new ValueComparator();
    }

    public function testCompareEntityReferencesWithSameIdAndClass(): void
    {
        $entity1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $entity2 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 456];
        
        $result = $this->comparator->compareEntityReferences($entity1, $entity2);
        
        self::assertTrue($result);
    }

    public function testCompareEntityReferencesWithDifferentIds(): void
    {
        $entity1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $entity2 = ['__entity__' => User::class, '__id__' => 2, '__hash__' => 123];
        
        $result = $this->comparator->compareEntityReferences($entity1, $entity2);
        
        self::assertFalse($result);
    }

    public function testCompareEntityReferencesWithDifferentClasses(): void
    {
        $entity1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $entity2 = ['__entity__' => Unit::class, '__id__' => 1, '__hash__' => 123];
        
        $result = $this->comparator->compareEntityReferences($entity1, $entity2);
        
        self::assertFalse($result);
    }

    public function testCompareEntityReferencesWithNullIds(): void
    {
        $entity1 = ['__entity__' => User::class, '__id__' => null, '__hash__' => 123];
        $entity2 = ['__entity__' => User::class, '__id__' => null, '__hash__' => 123];
        
        $result = $this->comparator->compareEntityReferences($entity1, $entity2);
        
        self::assertTrue($result);
    }

    public function testCompareEntityReferencesWithNullIdsDifferentHashes(): void
    {
        $entity1 = ['__entity__' => User::class, '__id__' => null, '__hash__' => 123];
        $entity2 = ['__entity__' => User::class, '__id__' => null, '__hash__' => 456];
        
        $result = $this->comparator->compareEntityReferences($entity1, $entity2);
        
        self::assertFalse($result);
    }

    public function testCompareEntityReferencesWithOneNullId(): void
    {
        $entity1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $entity2 = ['__entity__' => User::class, '__id__' => null, '__hash__' => 123];
        
        $result = $this->comparator->compareEntityReferences($entity1, $entity2);
        
        self::assertFalse($result);
    }

    public function testCompareObjectReferencesWithSameClassAndHash(): void
    {
        $object1 = ['__object__' => \stdClass::class, '__hash__' => 123];
        $object2 = ['__object__' => \stdClass::class, '__hash__' => 123];
        
        $result = $this->comparator->compareObjectReferences($object1, $object2);
        
        self::assertTrue($result);
    }

    public function testCompareObjectReferencesWithDifferentClasses(): void
    {
        $object1 = ['__object__' => \stdClass::class, '__hash__' => 123];
        $object2 = ['__object__' => \DateTime::class, '__hash__' => 123];
        
        $result = $this->comparator->compareObjectReferences($object1, $object2);
        
        self::assertFalse($result);
    }

    public function testCompareObjectReferencesWithDifferentHashes(): void
    {
        $object1 = ['__object__' => \stdClass::class, '__hash__' => 123];
        $object2 = ['__object__' => \stdClass::class, '__hash__' => 456];
        
        $result = $this->comparator->compareObjectReferences($object1, $object2);
        
        self::assertFalse($result);
    }

    public function testCompareCollectionsWithSameItems(): void
    {
        $item1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $item2 = ['__entity__' => User::class, '__id__' => 2, '__hash__' => 456];
        
        $collection1 = [
            '__collection__' => true,
            '__items__' => [$item1, $item2]
        ];
        $collection2 = [
            '__collection__' => true,
            '__items__' => [$item1, $item2]
        ];
        
        $result = $this->comparator->compareCollections($collection1, $collection2);
        
        self::assertTrue($result);
    }

    public function testCompareCollectionsWithDifferentOrder(): void
    {
        $item1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $item2 = ['__entity__' => User::class, '__id__' => 2, '__hash__' => 456];
        
        $collection1 = [
            '__collection__' => true,
            '__items__' => [$item1, $item2]
        ];
        $collection2 = [
            '__collection__' => true,
            '__items__' => [$item2, $item1]
        ];
        
        $result = $this->comparator->compareCollections($collection1, $collection2);
        
        self::assertTrue($result);
    }

    public function testCompareCollectionsWithDifferentSizes(): void
    {
        $item1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $item2 = ['__entity__' => User::class, '__id__' => 2, '__hash__' => 456];
        
        $collection1 = [
            '__collection__' => true,
            '__items__' => [$item1]
        ];
        $collection2 = [
            '__collection__' => true,
            '__items__' => [$item1, $item2]
        ];
        
        $result = $this->comparator->compareCollections($collection1, $collection2);
        
        self::assertFalse($result);
    }

    public function testCompareCollectionsWithDifferentItems(): void
    {
        $item1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $item2 = ['__entity__' => User::class, '__id__' => 2, '__hash__' => 456];
        $item3 = ['__entity__' => User::class, '__id__' => 3, '__hash__' => 789];
        
        $collection1 = [
            '__collection__' => true,
            '__items__' => [$item1, $item2]
        ];
        $collection2 = [
            '__collection__' => true,
            '__items__' => [$item1, $item3]
        ];
        
        $result = $this->comparator->compareCollections($collection1, $collection2);
        
        self::assertFalse($result);
    }

    public function testCompareEmptyCollections(): void
    {
        $collection1 = [
            '__collection__' => true,
            '__items__' => []
        ];
        $collection2 = [
            '__collection__' => true,
            '__items__' => []
        ];
        
        $result = $this->comparator->compareCollections($collection1, $collection2);
        
        self::assertTrue($result);
    }

    public function testCompareCollectionsWithMixedEntityTypes(): void
    {
        $userItem = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $unitItem = ['__entity__' => Unit::class, '__id__' => 1, '__hash__' => 456];
        
        $collection1 = [
            '__collection__' => true,
            '__items__' => [$userItem, $unitItem]
        ];
        $collection2 = [
            '__collection__' => true,
            '__items__' => [$unitItem, $userItem]
        ];
        
        $result = $this->comparator->compareCollections($collection1, $collection2);
        
        self::assertTrue($result);
    }

    public function testCompareCollectionsWithNullIds(): void
    {
        $item1 = ['__entity__' => User::class, '__id__' => null, '__hash__' => 123];
        $item2 = ['__entity__' => User::class, '__id__' => null, '__hash__' => 456];
        
        $collection1 = [
            '__collection__' => true,
            '__items__' => [$item1, $item2]
        ];
        $collection2 = [
            '__collection__' => true,
            '__items__' => [$item2, $item1]
        ];
        
        $result = $this->comparator->compareCollections($collection1, $collection2);
        
        self::assertTrue($result);
    }

    public function testCompareCollectionsWithMixedNullAndNonNullIds(): void
    {
        $item1 = ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123];
        $item2 = ['__entity__' => User::class, '__id__' => null, '__hash__' => 456];
        
        $collection1 = [
            '__collection__' => true,
            '__items__' => [$item1, $item2]
        ];
        $collection2 = [
            '__collection__' => true,
            '__items__' => [$item2, $item1]
        ];
        
        $result = $this->comparator->compareCollections($collection1, $collection2);
        
        self::assertTrue($result);
    }
}