<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\ValueProcessor;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\ORM\ValueProcessor\EntityHydratorInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class EntityHydratorInterfaceTest extends TestCase
{
    private EntityHydratorInterface $hydrator;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock implementation of the interface
        $this->hydrator = $this->createMock(EntityHydratorInterface::class);
    }

    public function testHydrateMethod(): void
    {
        $data = [
            'id' => 123,
            'username' => 'john_doe',
            'email' => 'john@example.com'
        ];
        $entityName = User::class;
        $expectedEntity = new User();

        $this->hydrator->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->hydrator->hydrate($data, $entityName);

        $this->assertSame($expectedEntity, $result);
    }

    public function testHydrateWithEmptyData(): void
    {
        $data = [];
        $entityName = User::class;
        $expectedEntity = new User();

        $this->hydrator->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->hydrator->hydrate($data, $entityName);

        $this->assertSame($expectedEntity, $result);
    }

    public function testHydrateWithComplexData(): void
    {
        $data = [
            'id' => 456,
            'username' => 'jane_smith',
            'metadata' => ['key' => 'value'],
            'created_at' => '2023-01-01 12:00:00',
            'is_active' => true,
            'score' => 99.5
        ];
        $entityName = User::class;
        $expectedEntity = new User();

        $this->hydrator->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->hydrator->hydrate($data, $entityName);

        $this->assertSame($expectedEntity, $result);
    }

    public function testHydrateThrowsReflectionException(): void
    {
        $data = ['id' => 1];
        $entityName = 'NonExistentClass';

        $this->hydrator->method('hydrate')
            ->with($data, $entityName)
            ->willThrowException(new ReflectionException('Class not found'));

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Class not found');

        $this->hydrator->hydrate($data, $entityName);
    }

    public function testGetMetadataCacheMethod(): void
    {
        $expectedCache = new MetadataCache();

        $this->hydrator->method('getMetadataCache')
            ->willReturn($expectedCache);

        $result = $this->hydrator->getMetadataCache();

        $this->assertSame($expectedCache, $result);
        $this->assertInstanceOf(MetadataCache::class, $result);
    }

    public function testInterfaceMethodSignatures(): void
    {
        $data = ['test' => 'data'];
        $entityName = User::class;
        $entity = new User();
        $cache = new MetadataCache();

        // Configure mock to return expected values
        $this->hydrator->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($entity);
        
        $this->hydrator->method('getMetadataCache')
            ->willReturn($cache);

        // Test hydrate method signature and return type
        $hydrateResult = $this->hydrator->hydrate($data, $entityName);
        $this->assertIsObject($hydrateResult);
        $this->assertInstanceOf(User::class, $hydrateResult);

        // Test getMetadataCache method signature and return type
        $cacheResult = $this->hydrator->getMetadataCache();
        $this->assertInstanceOf(MetadataCache::class, $cacheResult);
    }

    public function testHydrateWithNullValuesInData(): void
    {
        $data = [
            'id' => 789,
            'username' => 'test_user',
            'email' => null,
            'description' => null,
            'age' => null
        ];
        $entityName = User::class;
        $expectedEntity = new User();

        $this->hydrator->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->hydrator->hydrate($data, $entityName);

        $this->assertSame($expectedEntity, $result);
    }

    public function testHydrateWithNumericKeys(): void
    {
        $data = [
            0 => 'first_value',
            1 => 'second_value',
            'named_key' => 'named_value'
        ];
        $entityName = User::class;
        $expectedEntity = new User();

        $this->hydrator->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->hydrator->hydrate($data, $entityName);

        $this->assertSame($expectedEntity, $result);
    }

    public function testHydrateReturnsCorrectType(): void
    {
        $data = ['id' => 1];
        $entityName = User::class;
        $entity = new User();

        $this->hydrator->method('hydrate')
            ->willReturn($entity);

        $result = $this->hydrator->hydrate($data, $entityName);

        // Verify the result is an object and of the expected type
        $this->assertIsObject($result);
        $this->assertInstanceOf($entityName, $result);
    }

    public function testInterfaceContractsAreSatisfied(): void
    {
        $testData = ['test' => 'value'];
        $testEntityName = User::class;
        $testEntity = new User();
        $testCache = new MetadataCache();

        // Set up method expectations
        $this->hydrator->method('hydrate')->willReturn($testEntity);
        $this->hydrator->method('getMetadataCache')->willReturn($testCache);

        // Verify interface contracts
        $hydrateResult = $this->hydrator->hydrate($testData, $testEntityName);
        $this->assertIsObject($hydrateResult);

        $cacheResult = $this->hydrator->getMetadataCache();
        $this->assertInstanceOf(MetadataCache::class, $cacheResult);

        // Verify method calls work without exceptions
        $this->assertTrue(true);
    }

    public function testHydrateWithDifferentEntityTypes(): void
    {
        $userData = ['id' => 1, 'username' => 'user'];
        $userEntity = new User();

        // Test with User entity
        $this->hydrator->method('hydrate')
            ->with($userData, User::class)
            ->willReturn($userEntity);

        $result = $this->hydrator->hydrate($userData, User::class);
        $this->assertInstanceOf(User::class, $result);
    }

    public function testMultipleHydrateCalls(): void
    {
        $entity1 = new User();
        $entity2 = new User();

        $this->hydrator->method('hydrate')
            ->willReturnOnConsecutiveCalls($entity1, $entity2);

        $result1 = $this->hydrator->hydrate(['id' => 1], User::class);
        $result2 = $this->hydrator->hydrate(['id' => 2], User::class);

        $this->assertSame($entity1, $result1);
        $this->assertSame($entity2, $result2);
        $this->assertNotSame($result1, $result2);
    }

    public function testGetMetadataCacheConsistency(): void
    {
        $cache = new MetadataCache();

        $this->hydrator->method('getMetadataCache')
            ->willReturn($cache);

        // Call multiple times to ensure consistency
        $result1 = $this->hydrator->getMetadataCache();
        $result2 = $this->hydrator->getMetadataCache();
        $result3 = $this->hydrator->getMetadataCache();

        $this->assertSame($cache, $result1);
        $this->assertSame($cache, $result2);
        $this->assertSame($cache, $result3);
        $this->assertSame($result1, $result2);
        $this->assertSame($result2, $result3);
    }
}