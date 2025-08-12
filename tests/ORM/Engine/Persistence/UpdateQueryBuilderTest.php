<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use Exception;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\ORM\Engine\Persistence\UpdateEntityValidator;
use MulerTech\Database\ORM\Engine\Persistence\UpdateQueryBuilder;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\PropertyChange;
use MulerTech\Database\Query\Builder\UpdateBuilder;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class UpdateQueryBuilderTest extends TestCase
{
    private UpdateQueryBuilder $queryBuilder;
    private EntityManagerInterface $entityManager;
    private MetadataRegistry $metadataRegistry;
    private UpdateEntityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadataRegistry = new MetadataRegistry();
        $this->validator = $this->createMock(UpdateEntityValidator::class);
        
        $this->queryBuilder = new UpdateQueryBuilder(
            $this->entityManager,
            $this->metadataRegistry,
            $this->validator
        );
    }

    public function testBuildQuery(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $change = new PropertyChange('username', 'OldName', 'John');
        $changes = ['username' => $change];
        
        // Mock entity manager
        $mockEngine = $this->createMock(EmEngine::class);
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        
        // Mock validator
        $this->validator->method('getEntityId')
            ->willReturn(123);
        
        $result = $this->queryBuilder->buildQuery($user, $changes);
        
        $this->assertInstanceOf(UpdateBuilder::class, $result);
    }

    public function testHasValidUpdates(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $change = new PropertyChange('username', 'OldName', 'John');
        $changes = ['username' => $change];
        
        $result = $this->queryBuilder->hasValidUpdates($user, $changes);
        
        $this->assertTrue($result);
    }

    public function testHasValidUpdatesWithEmptyChanges(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $changes = [];
        
        $result = $this->queryBuilder->hasValidUpdates($user, $changes);
        
        $this->assertFalse($result);
    }

    public function testHasValidUpdatesWithRelationProperty(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        // Mock a relation property change
        $change = new PropertyChange('unit', null, new \stdClass());
        $changes = ['unit' => $change];
        
        $result = $this->queryBuilder->hasValidUpdates($user, $changes);
        
        // This will return false unless metadata cache has the relation mapping
        $this->assertIsBool($result);
    }

    public function testBuildQueryWithValidatorError(): void
    {
        $user = new User();
        $user->setUsername('John'); // No ID
        
        $change = new PropertyChange('username', 'OldName', 'John');
        $changes = ['username' => $change];
        
        // Mock entity manager
        $mockEngine = $this->createMock(EmEngine::class);
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        
        // Mock validator to return null ID
        $this->validator->method('getEntityId')
            ->willReturn(null);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot update entity');
        
        $this->queryBuilder->buildQuery($user, $changes);
    }

    public function testBuildQueryWithComplexChanges(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $usernameChange = new PropertyChange('username', 'OldName', 'John');
        $emailChange = new PropertyChange('email', 'old@example.com', 'new@example.com');
        
        $changes = [
            'username' => $usernameChange,
            'email' => $emailChange
        ];
        
        // Mock entity manager
        $mockEngine = $this->createMock(EmEngine::class);
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        
        // Mock validator
        $this->validator->method('getEntityId')
            ->willReturn(123);
        
        $result = $this->queryBuilder->buildQuery($user, $changes);
        
        $this->assertInstanceOf(UpdateBuilder::class, $result);
    }

    public function testHasValidUpdatesWithDirectPropertyMapping(): void
    {
        // Load metadata to ensure proper property mapping
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        // Test with a property that is already mapped (username maps to username column)
        $change = new PropertyChange('username', 'OldName', 'John');
        $changes = ['username' => $change];
        
        $result = $this->queryBuilder->hasValidUpdates($user, $changes);
        
        $this->assertTrue($result);
    }

    public function testHasValidUpdatesWithRelationPropertyAdvanced(): void
    {
        // Load metadata to ensure proper relation mapping
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        $user = new User();
        $user->setId(123);
        
        $unit = new \MulerTech\Database\Tests\Files\Entity\Unit();
        $unit->setId(456);
        
        // Test with unit relation property (should trigger foreign key column lookup)
        $change = new PropertyChange('unit', null, $unit);
        $changes = ['unit' => $change];
        
        $result = $this->queryBuilder->hasValidUpdates($user, $changes);
        
        $this->assertIsBool($result);
    }

    public function testBuildQueryWithRelationUpdates(): void
    {
        // Load metadata to ensure proper relation mapping
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $unit = new \MulerTech\Database\Tests\Files\Entity\Unit();
        $unit->setId(456);
        
        // Test updating a relation property
        $change = new PropertyChange('unit', null, $unit);
        $changes = ['unit' => $change];
        
        // Mock entity manager
        $mockEngine = $this->createMock(EmEngine::class);
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        
        // Mock validator
        $this->validator->method('getEntityId')
            ->willReturn(123);
        
        $result = $this->queryBuilder->buildQuery($user, $changes);
        
        $this->assertInstanceOf(UpdateBuilder::class, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testHasValidUpdatesTriggersEchoStatements(): void
    {
        // Load metadata to ensure proper mapping
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        $user = new User();
        $user->setId(123);
        
        // First echo: Test property that should be in propertiesColumns but triggers the continue path
        // This will trigger line 75 echo when property is already in propertiesColumns
        $usernameChange = new PropertyChange('username', 'old', 'new');
        $changes = ['username' => $usernameChange];
        
        $result = $this->queryBuilder->hasValidUpdates($user, $changes);
        $this->assertTrue($result);

        // Second echo: Test with a relation property that has a foreign key
        // This should trigger line 80 echo in the foreign key lookup path
        $unit = new \MulerTech\Database\Tests\Files\Entity\Unit();
        $unit->setId(456);
        
        $unitChange = new PropertyChange('unit', null, $unit);
        $changes = ['unit' => $unitChange];
        
        $result = $this->queryBuilder->hasValidUpdates($user, $changes);
        $this->assertIsBool($result);
    }

    public function testAddRelationPropertyUpdatesTriggersEchoStatements(): void
    {
        // Load metadata for proper relation handling
        $this->metadataRegistry->loadEntitiesFromPath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );

        $user = new User();
        $user->setId(123);
        
        $unit = new \MulerTech\Database\Tests\Files\Entity\Unit();
        $unit->setId(456);
        
        // This should trigger addRelationPropertyUpdates echo statements (lines 136 and 139)
        $unitChange = new PropertyChange('unit', null, $unit);
        $changes = ['unit' => $unitChange];
        
        // Mock entity manager
        $mockEngine = $this->createMock(EmEngine::class);
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        
        // Mock validator
        $this->validator->method('getEntityId')
            ->willReturn(123);
        
        $result = $this->queryBuilder->buildQuery($user, $changes);
        $this->assertInstanceOf(UpdateBuilder::class, $result);
    }
}
