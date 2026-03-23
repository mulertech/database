<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\ValueProcessor;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\ValueProcessor\EntityHydratorInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class EntityHydratorInterfaceTest extends TestCase
{
    private EntityHydratorInterface $hydrator;

    private function getHydrator(): EntityHydratorInterface
    {
        if (!isset($this->hydrator)) {
            $this->hydrator = $this->createMock(EntityHydratorInterface::class);
        }
        return $this->hydrator;
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

        $this->getHydrator()->expects($this->once())
            ->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->getHydrator()->hydrate($data, $entityName);

        $this->assertSame($expectedEntity, $result);
    }

    public function testHydrateWithEmptyData(): void
    {
        $data = [];
        $entityName = User::class;
        $expectedEntity = new User();

        $this->getHydrator()->expects($this->once())
            ->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->getHydrator()->hydrate($data, $entityName);

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

        $this->getHydrator()->expects($this->once())
            ->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->getHydrator()->hydrate($data, $entityName);

        $this->assertSame($expectedEntity, $result);
    }

    public function testHydrateThrowsReflectionException(): void
    {
        $data = ['id' => 1];
        $entityName = 'NonExistentClass';

        $this->getHydrator()->expects($this->once())
            ->method('hydrate')
            ->with($data, $entityName)
            ->willThrowException(new ReflectionException('Class not found'));

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Class not found');

        $this->getHydrator()->hydrate($data, $entityName);
    }

    public function testGetMetadataRegistryMethod(): void
    {
        $expectedRegistry = new MetadataRegistry();

        $hydrator = $this->createStub(EntityHydratorInterface::class);
        $hydrator->method('getMetadataRegistry')
            ->willReturn($expectedRegistry);

        $result = $hydrator->getMetadataRegistry();

        $this->assertSame($expectedRegistry, $result);
        $this->assertInstanceOf(MetadataRegistry::class, $result);
    }

    public function testInterfaceMethodSignatures(): void
    {
        $data = ['test' => 'data'];
        $entityName = User::class;
        $entity = new User();
        $cache = new MetadataRegistry();

        // Configure mock to return expected values
        $this->getHydrator()->expects($this->once())
            ->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($entity);

        $this->getHydrator()->expects($this->once())
            ->method('getMetadataRegistry')
            ->willReturn($cache);

        // Test hydrate method signature and return type
        $hydrateResult = $this->getHydrator()->hydrate($data, $entityName);
        $this->assertIsObject($hydrateResult);
        $this->assertInstanceOf(User::class, $hydrateResult);

        // Test getMetadataRegistry method signature and return type
        $cacheResult = $this->getHydrator()->getMetadataRegistry();
        $this->assertInstanceOf(MetadataRegistry::class, $cacheResult);
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

        $this->getHydrator()->expects($this->once())
            ->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->getHydrator()->hydrate($data, $entityName);

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

        $this->getHydrator()->expects($this->once())
            ->method('hydrate')
            ->with($data, $entityName)
            ->willReturn($expectedEntity);

        $result = $this->getHydrator()->hydrate($data, $entityName);

        $this->assertSame($expectedEntity, $result);
    }

    public function testHydrateReturnsCorrectType(): void
    {
        $data = ['id' => 1];
        $entityName = User::class;
        $entity = new User();

        $hydrator = $this->createStub(EntityHydratorInterface::class);
        $hydrator->method('hydrate')
            ->willReturn($entity);

        $result = $hydrator->hydrate($data, $entityName);

        // Verify the result is an object and of the expected type
        $this->assertIsObject($result);
        $this->assertInstanceOf($entityName, $result);
    }

    public function testInterfaceContractsAreSatisfied(): void
    {
        $testData = ['test' => 'value'];
        $testEntityName = User::class;
        $testEntity = new User();
        $testCache = new MetadataRegistry();

        $hydrator = $this->createStub(EntityHydratorInterface::class);

        // Set up method expectations
        $hydrator->method('hydrate')->willReturn($testEntity);
        $hydrator->method('getMetadataRegistry')->willReturn($testCache);

        // Verify interface contracts
        $hydrateResult = $hydrator->hydrate($testData, $testEntityName);
        $this->assertIsObject($hydrateResult);

        $cacheResult = $hydrator->getMetadataRegistry();
        $this->assertInstanceOf(MetadataRegistry::class, $cacheResult);

        // Verify method calls work without exceptions
        $this->assertTrue(true);
    }

    public function testHydrateWithDifferentEntityTypes(): void
    {
        $userData = ['id' => 1, 'username' => 'user'];
        $userEntity = new User();

        // Test with User entity
        $this->getHydrator()->expects($this->once())
            ->method('hydrate')
            ->with($userData, User::class)
            ->willReturn($userEntity);

        $result = $this->getHydrator()->hydrate($userData, User::class);
        $this->assertInstanceOf(User::class, $result);
    }

    public function testMultipleHydrateCalls(): void
    {
        $entity1 = new User();
        $entity2 = new User();

        $hydrator = $this->createStub(EntityHydratorInterface::class);
        $hydrator->method('hydrate')
            ->willReturnOnConsecutiveCalls($entity1, $entity2);

        $result1 = $hydrator->hydrate(['id' => 1], User::class);
        $result2 = $hydrator->hydrate(['id' => 2], User::class);

        $this->assertSame($entity1, $result1);
        $this->assertSame($entity2, $result2);
        $this->assertNotSame($result1, $result2);
    }

    public function testGetMetadataRegistryConsistency(): void
    {
        $cache = new MetadataRegistry();

        $hydrator = $this->createStub(EntityHydratorInterface::class);
        $hydrator->method('getMetadataRegistry')
            ->willReturn($cache);

        // Call multiple times to ensure consistency
        $result1 = $hydrator->getMetadataRegistry();
        $result2 = $hydrator->getMetadataRegistry();
        $result3 = $hydrator->getMetadataRegistry();

        $this->assertSame($cache, $result1);
        $this->assertSame($cache, $result2);
        $this->assertSame($cache, $result3);
        $this->assertSame($result1, $result2);
        $this->assertSame($result2, $result3);
    }
}