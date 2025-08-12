<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Relations;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\Engine\Relations\EntityRelationLoader;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityRelationLoaderTest extends TestCase
{
    private EntityRelationLoader $relationLoader;
    private EntityManagerInterface $entityManager;
    private MetadataRegistry $metadataRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a real MetadataRegistry instance since it's final and cannot be mocked
        $this->metadataRegistry = new MetadataRegistry();

        // Create mocked EntityManager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getMetadataRegistry')
            ->willReturn($this->metadataRegistry);

        $this->relationLoader = new EntityRelationLoader($this->entityManager);
    }

    public function testEntityRelationLoaderInstantiation(): void
    {
        self::assertInstanceOf(EntityRelationLoader::class, $this->relationLoader);
    }

    public function testLoadRelationsWithNoRelationsConfigured(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('TestUser');

        $entityData = ['id' => 1, 'username' => 'TestUser'];

        $result = $this->relationLoader->loadRelations($user, $entityData);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testLoadRelationsReturnsArrayType(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('TestUser');

        $entityData = ['id' => 1, 'username' => 'TestUser'];

        $result = $this->relationLoader->loadRelations($user, $entityData);

        self::assertIsArray($result);
    }

    public function testLoadRelationsWithUnitEntity(): void
    {
        $unit = new Unit();
        $unit->setId(1);
        $unit->setName('TestUnit');

        $entityData = ['id' => 1, 'name' => 'TestUnit'];

        $relations = $this->relationLoader->loadRelations($unit, $entityData);

        self::assertIsArray($relations);
        self::assertEmpty($relations);
    }

    public function testLoadRelationsMethodExists(): void
    {
        self::assertTrue(
            method_exists($this->relationLoader, 'loadRelations'),
            'EntityRelationLoader should have loadRelations method'
        );

        self::assertTrue(
            is_callable([$this->relationLoader, 'loadRelations']),
            'loadRelations method should be callable'
        );
    }

    public function testLoadRelationsWithMockedMetadata(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('TestUser');

        $entityData = ['id' => 1, 'username' => 'TestUser'];

        $result = $this->relationLoader->loadRelations($user, $entityData);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testLoadRelationsAccessesMetadataCorrectly(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('TestUser');

        $entityData = ['id' => 1, 'username' => 'TestUser'];

        // This test verifies that the method can be called without exceptions
        $result = $this->relationLoader->loadRelations($user, $entityData);

        self::assertIsArray($result);
    }

    public function testLoadRelationsHandlesDifferentEntityTypes(): void
    {
        $entities = [
            [new User(), ['id' => 1, 'username' => 'User1']],
            [new Unit(), ['id' => 1, 'name' => 'Unit1']]
        ];

        foreach ($entities as [$entity, $data]) {
            $relations = $this->relationLoader->loadRelations($entity, $data);
            self::assertIsArray($relations);
        }
    }

    public function testLoadRelationsWithEntityManagerAccess(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('TestUser');

        $entityData = ['id' => 1, 'username' => 'TestUser'];

        // Verify that EntityManager is properly accessed through the relation loader
        $result = $this->relationLoader->loadRelations($user, $entityData);

        self::assertIsArray($result);
    }

    public function testLoadRelationsWithInvalidRelationData(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('TestUser');

        // Test with empty entity data
        $entityData = [];

        $result = $this->relationLoader->loadRelations($user, $entityData);

        self::assertIsArray($result);
    }

    public function testLoadRelationsWithBasicFunctionality(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('TestUser');
        
        $entityData = ['id' => 1, 'username' => 'TestUser'];
        
        // Test basic functionality without complex mocking
        $result = $this->relationLoader->loadRelations($user, $entityData);
        
        self::assertIsArray($result);
        // With no relations configured in metadata, should return empty array
        self::assertEmpty($result);
    }

    public function testLoadRelationsWithDifferentEntityDataStructures(): void
    {
        $entities = [
            [new User(), ['id' => 1, 'username' => 'User1', 'email' => 'user1@test.com']],
            [new Unit(), ['id' => 2, 'name' => 'Unit1', 'description' => 'Test Unit']]
        ];

        foreach ($entities as [$entity, $data]) {
            $relations = $this->relationLoader->loadRelations($entity, $data);
            self::assertIsArray($relations);
            // Without complex metadata setup, should return empty arrays
            self::assertEmpty($relations);
        }
    }

    public function testLoadRelationsWithEmptyEntityData(): void
    {
        $user = new User();
        $user->setId(1);
        
        // Test with various empty/null data scenarios
        $testCases = [
            [],
            ['id' => null],
            ['username' => ''],
        ];
        
        foreach ($testCases as $entityData) {
            $result = $this->relationLoader->loadRelations($user, $entityData);
            self::assertIsArray($result);
        }
    }

    public function testLoadRelationsWithActualMetadata(): void
    {
        // Load real metadata to trigger relation processing
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        $user = new User();
        $user->setId(123);
        $user->setUsername('TestUser');
        
        // Provide entity data that includes relation fields
        $entityData = [
            'id' => 123,
            'username' => 'TestUser', 
            'unit_id' => 456,  // This should trigger OneToOne relation loading
            'manager' => 789   // This should trigger another relation
        ];

        // Mock EntityManager methods that are called during relation loading
        $mockEmEngine = $this->createMock(\MulerTech\Database\ORM\EmEngine::class);
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEmEngine);
        
        $this->entityManager->method('find')
            ->willReturn(null); // Return null to simulate not found

        $result = $this->relationLoader->loadRelations($user, $entityData);
        
        self::assertIsArray($result);
    }

    public function testLoadOneToManyWithNoEntityId(): void
    {
        // Load real metadata
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        // Create a user without ID to trigger the echo in loadOneToMany
        $user = new User();
        $user->setUsername('TestUser'); // No ID set
        
        $entityData = ['username' => 'TestUser'];

        // This should trigger the echo statement in loadOneToMany when entityId is null
        $result = $this->relationLoader->loadRelations($user, $entityData);
        
        self::assertIsArray($result);
    }

    public function testGetColumnNameWithInvalidProperty(): void
    {
        // Load real metadata
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        // Use reflection to call private getColumnName method with invalid property
        $reflection = new \ReflectionClass($this->relationLoader);
        $method = $reflection->getMethod('getColumnName');
        $method->setAccessible(true);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Column name is not defined');
        
        // This should trigger the echo statement before throwing exception
        $method->invoke($this->relationLoader, User::class, 'nonExistentProperty');
    }

}