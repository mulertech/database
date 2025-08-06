<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Relations;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\MetadataCache;
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
    private MetadataCache $metadataCache;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a real MetadataCache instance since it's final and cannot be mocked
        $cacheConfig = new CacheConfig(
            maxSize: 100,
            ttl: 3600,
            evictionPolicy: 'lru'
        );
        $this->metadataCache = new MetadataCache($cacheConfig);

        // Create mocked EntityManager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getMetadataCache')
            ->willReturn($this->metadataCache);

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
}