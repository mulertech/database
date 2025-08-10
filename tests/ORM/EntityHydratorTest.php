<?php

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\ORM\EntityHydrator;
use MulerTech\Database\ORM\Exception\HydrationException;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\EntityNotMapped\EntityWithNonNullableProperty;
use MulerTech\Database\Tests\Files\EntityNotMapped\EntityWithMissingSetter;
use MulerTech\Database\Tests\Files\EntityNotMapped\EntityWithMissingGetter;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * Tests for the EntityHydrator class
 */
class EntityHydratorTest extends TestCase
{
    private EntityHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new EntityHydrator(new MetadataCache());
    }

    /**
     * Test hydration with entity metadata and setters
     */
    public function testHydrateWithEntityMetadataAndSetters(): void
    {
        $data = [
            'id' => '42',
            'username' => 'Test User', // Changed from 'user_name' to 'username'
            'size' => '183',
            'account_balance' => '100.50',
        ];

        $hydratedEntity = $this->hydrator->hydrate($data, User::class);

        $this->assertSame(42, $hydratedEntity->getId());
        $this->assertSame('Test User', $hydratedEntity->getUsername()); // Changed from getUserName to getUsername
        $this->assertSame(183, $hydratedEntity->getSize());
        $this->assertSame(100.50, $hydratedEntity->getAccountBalance());
    }

    /**
     * Test hydration with invalid column type in entity metadata
     */
    public function testHydrateWithInvalidColumnType(): void
    {
        $data = [
            'id' => 'invalid-int',
        ];

        $hydratedEntity = $this->hydrator->hydrate($data, User::class);

        // The invalid value should be coerced to 0
        $this->assertSame(0, $hydratedEntity->getId());
    }

    /**
     * Test hydration with null values for nullable properties
     * @throws ReflectionException
     */
    public function testHydrateWithNullValues(): void
    {
        $data = [
            'size' => null, // This is nullable in the User entity
        ];

        // The hydrator should handle null values gracefully for nullable properties
        $hydratedEntity = $this->hydrator->hydrate($data, User::class);
        
        $this->assertNull($hydratedEntity->getSize());
    }

    public function testGetMetadataCache(): void
    {
        $metadataCache = new MetadataCache();
        $hydrator = new EntityHydrator($metadataCache);
        
        self::assertSame($metadataCache, $hydrator->getMetadataCache());
    }

    public function testExtract(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('testuser');
        $user->setSize(180);
        $user->setAccountBalance(250.75);
        
        $extractedData = $this->hydrator->extract($user);
        
        self::assertIsArray($extractedData);
        self::assertEquals(123, $extractedData['id']);
        self::assertEquals('testuser', $extractedData['username']);
        self::assertEquals(180, $extractedData['size']);
        self::assertEquals(250.75, $extractedData['account_balance']);
    }

    public function testExtractWithNullValues(): void
    {
        $user = new User();
        // Don't set any values, so they remain null
        
        $extractedData = $this->hydrator->extract($user);
        
        self::assertIsArray($extractedData);
        self::assertNull($extractedData['id']);
        self::assertNull($extractedData['username']);
        self::assertNull($extractedData['size']);
        self::assertNull($extractedData['account_balance']);
    }

    public function testHydrateWithRelationProperty(): void
    {
        // Test with relation properties that should be skipped
        $data = [
            'id' => 42,
            'username' => 'testuser',
            'unit_id' => 1, // This is a relation property
        ];

        $hydratedEntity = $this->hydrator->hydrate($data, User::class);

        self::assertEquals(42, $hydratedEntity->getId());
        self::assertEquals('testuser', $hydratedEntity->getUsername());
        // unit should remain null since it's a relation property
        self::assertNull($hydratedEntity->getUnit());
    }

    public function testProcessValueWithNullInput(): void
    {
        $metadataCache = new MetadataCache();
        $metadata = $metadataCache->getEntityMetadata(User::class);
        
        $result = $this->hydrator->processValue($metadata, 'username', null);
        
        self::assertNull($result);
    }

    public function testProcessValueWithValidInput(): void
    {
        $metadataCache = new MetadataCache();
        $metadata = $metadataCache->getEntityMetadata(User::class);
        
        $result = $this->hydrator->processValue($metadata, 'username', 'test_value');
        
        self::assertEquals('test_value', $result);
    }

    /**
     * Test hydration throws exception when trying to hydrate null into a non-nullable property
     */
    public function testHydrateThrowsExceptionForNonNullableProperty(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Property requiredField of MulerTech\Database\Tests\Files\EntityNotMapped\EntityWithNonNullableProperty cannot be null');

        $data = [
            'required_field' => null, // Trying to set null to a non-nullable property
            'optional_field' => 'valid_value',
        ];

        $this->hydrator->hydrate($data, EntityWithNonNullableProperty::class);
    }

    /**
     * Test hydration succeeds when nullable property is null but non-nullable property has value
     */
    public function testHydrateSucceedsWithValidNonNullableValue(): void
    {
        $data = [
            'required_field' => 'required_value',
            'optional_field' => null, // This should be fine since it's nullable
        ];

        $entity = $this->hydrator->hydrate($data, EntityWithNonNullableProperty::class);

        self::assertEquals('required_value', $entity->getRequiredField());
        self::assertNull($entity->getOptionalField());
    }

    /**
     * Test hydration throws exception when setter method is missing
     * This addresses the TODO at line 77 in EntityHydrator
     */
    public function testHydrateThrowsExceptionForMissingSetter(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('No setter defined for property \'name\' in entity \'MulerTech\Database\Tests\Files\EntityNotMapped\EntityWithMissingSetter\'');

        $data = [
            'id' => 1,
            'name' => 'test name',
        ];

        $this->hydrator->hydrate($data, EntityWithMissingSetter::class);
    }

    /**
     * Test extract throws exception when getter method is missing
     * This addresses the TODO at line 106 in EntityHydrator
     */
    public function testExtractThrowsExceptionForMissingGetter(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('No getter defined for property \'description\' in entity \'MulerTech\Database\Tests\Files\EntityNotMapped\EntityWithMissingGetter\'');

        $entity = new EntityWithMissingGetter();
        $entity->setId(1);
        $entity->setDescription('test description');

        $this->hydrator->extract($entity);
    }
}
