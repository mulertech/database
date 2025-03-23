<?php

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Mapping\MtOneToMany;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Entity\Group;
use MulerTech\Database\Tests\Files\Entity\GroupUser;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class DatabaseCollectionTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MtOneToMany $oneToManyMapping;
    private MtManyToMany $manyToManyMapping;
    private array $testEntities;

    protected function setUp(): void
    {
        // Create mock objects for dependencies
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        // Create test entities (simple stdClass objects for testing)
        $this->testEntities = [
            (new Group())->setId(1),
            (new Group())->setId(2),
            (new Group())->setId(3),
        ];
        
        // Set up mappings
        $this->oneToManyMapping = new MtOneToMany(
            entity: Group::class,
            targetEntity: Group::class,
            mappedBy: "parent_id"
        );

        $this->manyToManyMappingWithMappedBy = new MtManyToMany(
            entity: User::class,
            targetEntity: Group::class,
            mappedBy: GroupUser::class,
            joinColumn: "user_id",
            inverseJoinColumn: "group_id"
        );
        $this->manyToManyMappingWithJoinTable = new MtManyToMany(
            entity: User::class,
            targetEntity: Group::class,
            joinTable: "user_groups",
            joinColumn: "user_id",
            inverseJoinColumn: "group_id"
        );
    }

    public function testConstructorWithOneToManyMapping(): void
    {
        $collection = new DatabaseCollection(
            $this->entityManager,
            $this->oneToManyMapping,
            $this->testEntities
        );
        
        $this->assertCount(3, $collection);
        $this->assertFalse($collection->hasChanges());
    }
    
    public function testConstructorWithManyToManyMapping(): void
    {
        $collection = new DatabaseCollection(
            $this->entityManager,
            $this->manyToManyMappingWithMappedBy,
            $this->testEntities
        );
        
        $this->assertCount(3, $collection);
        $this->assertFalse($collection->hasChanges());
    }
    
    public function testConstructorThrowsExceptionWhenEntityIsNull(): void
    {
        $invalidMapping = $this->createMock(MtOneToMany::class);
        $invalidMapping->entity = null;
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The entity class name must be set in the relationalMapping.');
        
        new DatabaseCollection(
            $this->entityManager,
            $invalidMapping,
            $this->testEntities
        );
    }
    
    public function testGetAddedEntities(): void
    {
        $collection = new DatabaseCollection(
            $this->entityManager,
            $this->oneToManyMapping,
            $this->testEntities
        );
        
        $newEntity = new Group();
        $collection->push($newEntity);
        
        $added = $collection->getAddedEntities();
        $this->assertCount(1, $added);
        $this->assertSame($newEntity, current($added));
        $this->assertTrue($collection->hasChanges());
    }

    public function testGetAddedEntitiesWithEmptyCollection(): void
    {
        $collection = new DatabaseCollection(
            $this->entityManager,
            $this->oneToManyMapping,
            []
        );

        $newEntity = new Group();
        $collection->push($newEntity);

        $added = $collection->getAddedEntities();
        $this->assertCount(1, $added);
        $this->assertSame($newEntity, current($added));
        $this->assertTrue($collection->hasChanges());
    }

    public function testGetRemovedEntities(): void
    {
        $collection = new DatabaseCollection(
            $this->entityManager,
            $this->oneToManyMapping,
            $this->testEntities
        );
        
        $entityToRemove = $this->testEntities[1];
        $collection->removeItem($entityToRemove);
        $removed = $collection->getRemovedEntities();
        $this->assertCount(1, $removed);
        $this->assertSame($entityToRemove, current($removed));
        $this->assertTrue($collection->hasChanges());
    }
    
    public function testHasChangesWithMultipleOperations(): void
    {
        $collection = new DatabaseCollection(
            $this->entityManager,
            $this->manyToManyMappingWithMappedBy,
            $this->testEntities
        );
        
        // Initially no changes
        $this->assertFalse($collection->hasChanges());
        
        // Add a new entity
        $newEntity = new Group();
        $collection->push($newEntity);
        $this->assertTrue($collection->hasChanges());
        
        // Remove an existing entity
        $entityToRemove = $this->testEntities[0];
        $collection->removeItem($entityToRemove);
        
        // Should have both added and removed entities
        $this->assertCount(1, $collection->getAddedEntities());
        $this->assertCount(1, $collection->getRemovedEntities());
        $this->assertTrue($collection->hasChanges());
    }
    
    public function testNoChangesAfterAddAndRemoveSameEntity(): void
    {
        $collection = new DatabaseCollection(
            $this->entityManager,
            $this->manyToManyMappingWithJoinTable,
            $this->testEntities
        );
        
        // Add a new entity
        $newEntity = new Group();
        $collection->push($newEntity);
        
        // Then remove it
        $collection->removeItem($newEntity);
        
        // The added/removed should cancel out for this entity
        $this->assertCount(0, $collection->getAddedEntities());
        
        // But it's still tracked as a change in the removed collection since
        // it wasn't in the initial state
        $this->assertCount(0, $collection->getRemovedEntities());
        
        // Overall the collection should be unchanged
        $this->assertFalse($collection->hasChanges());
    }
}
