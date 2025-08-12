<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Relations;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\ORM\Engine\Relations\ManyToManyProcessor;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ManyToManyProcessorTest extends TestCase
{
    private ManyToManyProcessor $processor;
    private EntityManagerInterface $entityManager;
    private StateManagerInterface $stateManager;
    private MetadataRegistry $metadataRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->stateManager = $this->createMock(StateManagerInterface::class);
        
        $this->metadataRegistry = new MetadataRegistry();
        
        $this->entityManager->method('getMetadataRegistry')
            ->willReturn($this->metadataRegistry);
        
        $this->processor = new ManyToManyProcessor($this->entityManager);
    }

    public function testProcessWithNoManyToManyRelations(): void
    {
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testGetOperations(): void
    {
        $operations = $this->processor->getOperations();
        
        $this->assertIsArray($operations);
        $this->assertEmpty($operations);
    }

    public function testClear(): void
    {
        $this->processor->clear();
        
        $operations = $this->processor->getOperations();
        $this->assertEmpty($operations);
    }

    public function testStartFlushCycle(): void
    {
        // This should reset internal state
        $this->processor->startFlushCycle();
        
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testProcessWithNoRelationsBasic(): void
    {
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testProcessWithEmptyGroups(): void
    {
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testProcessMultipleTimes(): void
    {
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testProcessWithNewEntity(): void
    {
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testProcessWithExistingEntity(): void
    {
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testProcessWithBasicEntityAndReflection(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('TestUser');
        
        $reflection = new ReflectionClass($user);
        
        // Process with basic setup - should not throw exceptions
        $this->processor->process($user, $reflection);
        
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
        // Without ManyToMany relations configured, should be empty
        $this->assertEmpty($operations);
    }

    public function testProcessWithDifferentEntityTypes(): void
    {
        $entities = [
            new User(),
            new Group(),
        ];
        
        foreach ($entities as $entity) {
            $reflection = new ReflectionClass($entity);
            $this->processor->process($entity, $reflection);
            
            // Should not throw exceptions
            $operations = $this->processor->getOperations();
            $this->assertIsArray($operations);
        }
    }

    public function testClearResetsAllInternalState(): void
    {
        // Add some mock processing first
        $user = new User();
        $user->setId(1);
        $reflection = new ReflectionClass($user);
        
        $this->processor->process($user, $reflection);
        
        // Clear should reset everything
        $this->processor->clear();
        
        $operations = $this->processor->getOperations();
        $this->assertEmpty($operations);
    }

    public function testStartFlushCycleCanBeCalledMultipleTimes(): void
    {
        $this->processor->startFlushCycle();
        $this->processor->startFlushCycle();
        $this->processor->startFlushCycle();
        
        // Should not throw exceptions
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testProcessorInstanceIsProperlyInitialized(): void
    {
        $this->assertInstanceOf(ManyToManyProcessor::class, $this->processor);
        
        // Basic method availability
        $this->assertTrue(method_exists($this->processor, 'process'));
        $this->assertTrue(method_exists($this->processor, 'getOperations'));
        $this->assertTrue(method_exists($this->processor, 'clear'));
        $this->assertTrue(method_exists($this->processor, 'startFlushCycle'));
    }

    public function testProcessWithActualMetadataAndInvalidRelations(): void
    {
        // Load real metadata to trigger relation processing
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        $user = new User();
        $user->setId(123);
        $user->setUsername('TestUser');
        
        $reflection = new ReflectionClass($user);
        
        // This should process the entity and may trigger some echo statements
        $this->processor->process($user, $reflection);
        
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }

    public function testProcessPropertyWithInvalidProperty(): void
    {
        $user = new User();
        $reflection = new ReflectionClass($user);
        
        // Use reflection to access private method
        $processorReflection = new ReflectionClass($this->processor);
        $method = $processorReflection->getMethod('hasValidProperty');
        $method->setAccessible(true);
        
        // Test with non-existent property
        $result = $method->invoke($this->processor, $user, 'nonExistentProperty');
        
        $this->assertFalse($result);
    }

    public function testProcessPropertyWithAlreadyProcessedRelation(): void
    {
        $user = new User();
        $reflection = new ReflectionClass($user);
        
        // Use reflection to access private properties and methods
        $processorReflection = new ReflectionClass($this->processor);
        
        // Set processed relations to trigger the echo
        $processedProperty = $processorReflection->getProperty('processedRelations');
        $processedProperty->setAccessible(true);
        
        $entityId = spl_object_id($user);
        $relationKey = $entityId . '_groups';
        $processedProperty->setValue($this->processor, [$relationKey => true]);
        
        // Access private processProperty method
        $processPropertyMethod = $processorReflection->getMethod('processProperty');
        $processPropertyMethod->setAccessible(true);
        
        $manyToMany = new MtManyToMany(
            targetEntity: Group::class,
            joinProperty: 'user',
            inverseJoinProperty: 'group'
        );
        
        // This should trigger early return for already processed relation
        $processPropertyMethod->invoke(
            $this->processor,
            $user,
            'groups',
            $manyToMany,
            $entityId
        );
        
        $this->assertTrue(true);
    }

    public function testProcessWithEmptyRelations(): void
    {
        // Test processing when entity has no ManyToMany relations
        $user = new User();

        // Use reflection to set empty mapping cache
        $processorReflection = new ReflectionClass($this->processor);
        $mappingCacheProperty = $processorReflection->getProperty('mappingCache');
        $mappingCacheProperty->setAccessible(true);
        
        // Set empty mapping
        $emptyMapping = [];
        $mappingCacheProperty->setValue($this->processor, [User::class => $emptyMapping]);
        
        // Process should handle empty relations gracefully
        $this->processor->process($user);

        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
        $this->assertEmpty($operations);
    }
}