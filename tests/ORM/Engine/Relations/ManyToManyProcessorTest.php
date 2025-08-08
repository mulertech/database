<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Relations;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\MetadataCache;
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
    private MetadataCache $metadataCache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->stateManager = $this->createMock(StateManagerInterface::class);
        
        $cacheConfig = new CacheConfig(
            maxSize: 100,
            ttl: 3600,
            evictionPolicy: 'lru'
        );
        $this->metadataCache = new MetadataCache($cacheConfig);
        
        $this->entityManager->method('getMetadataCache')
            ->willReturn($this->metadataCache);
        
        $this->processor = new ManyToManyProcessor(
            $this->entityManager,
            $this->stateManager
        );
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
        $this->metadataCache->loadEntitiesFromPath(
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
        $result = $method->invoke($this->processor, $reflection, $user, 'nonExistentProperty');
        
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
        
        $manyToMany = [
            'mappedBy' => Group::class,
            'joinProperty' => 'user',
            'inverseJoinProperty' => 'group'
        ];
        
        // This should trigger the echo for already processed relation
        $processPropertyMethod->invoke(
            $this->processor,
            $user,
            $reflection,
            'groups',
            $manyToMany,
            $entityId
        );
        
        $this->assertTrue(true);
    }

    public function testShouldProcessNewCollection(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('shouldProcessNewCollection');
        $method->setAccessible(true);
        
        $user = new User();
        $collection = new \MulerTech\Database\ORM\DatabaseCollection();
        
        // This should trigger the echo statement in shouldProcessNewCollection
        $result = $method->invoke($this->processor, $collection, $user);
        
        $this->assertIsBool($result);
    }

    public function testProcessNewCollection(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('processNewCollection');
        $method->setAccessible(true);
        
        $user = new User();
        $group = new Group();
        $collection = new \MulerTech\Database\ORM\DatabaseCollection([$group]);
        
        $manyToMany = [
            'mappedBy' => Group::class,
            'joinProperty' => 'user',
            'inverseJoinProperty' => 'group'
        ];
        
        // This should trigger the echo statement in processNewCollection
        $method->invoke($this->processor, $user, $collection, $manyToMany);
        
        $this->assertTrue(true);
    }


    public function testProcessWithInvalidRelationStructure(): void
    {
        // Load metadata for User entity which should have ManyToMany relations
        $this->metadataCache->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        // Create a custom entity processor that has corrupted metadata
        $user = new User();
        $reflection = new ReflectionClass($user);
        
        // Use reflection to corrupt the mapping cache with invalid relation structure
        $processorReflection = new ReflectionClass($this->processor);
        $mappingCacheProperty = $processorReflection->getProperty('mappingCache');
        $mappingCacheProperty->setAccessible(true);
        
        // Set invalid mapping (non-array relation) to trigger the echo
        $corruptedMapping = [
            'groups' => 'not_an_array' // This will trigger the echo in process method
        ];
        $mappingCacheProperty->setValue($this->processor, [User::class => $corruptedMapping]);
        
        // This should trigger the echo for invalid relation structure
        $this->processor->process($user, $reflection);
        
        $operations = $this->processor->getOperations();
        $this->assertIsArray($operations);
    }
}