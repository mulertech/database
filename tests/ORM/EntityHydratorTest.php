<?php

namespace MulerTech\Database\Tests\ORM;

use DateTime;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\ORM\EntityHydrator;
use MulerTech\Database\Tests\Files\Entity\User;
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
     * Test hydration with DbMapping and setters
     */
    public function testHydrateWithDbMappingAndSetters(): void
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
     * Test hydration with invalid column type in DbMapping
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
     * Test hydration with null values
     * @throws ReflectionException
     */
    public function testHydrateWithNullValues(): void
    {
        $data = [
            'id' => null,
            'username' => null,
        ];

        // The hydrator should handle null values gracefully, not throw TypeError
        // since the properties are nullable in the entity
        $hydratedEntity = $this->hydrator->hydrate($data, User::class);
        
        $this->assertNull($hydratedEntity->getId());
        $this->assertNull($hydratedEntity->getUsername());
    }
}
