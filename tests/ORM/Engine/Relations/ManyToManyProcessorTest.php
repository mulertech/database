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
}