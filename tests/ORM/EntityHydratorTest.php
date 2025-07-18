<?php

namespace MulerTech\Database\Tests\ORM;

use DateTime;
use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\EntityHydrator;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use TypeError;

/**
 * Tests for the EntityHydrator class
 */
class EntityHydratorTest extends TestCase
{
    private EntityHydrator $hydrator;
    private DbMappingInterface $dbMapping;

    protected function setUp(): void
    {
        // Mock DbMappingInterface
        $this->dbMapping = new DbMapping(dirname(__DIR__) . '/Files/Entity');
        $this->hydrator = new EntityHydrator($this->dbMapping);
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
     * Test fallback to snake_case to camelCase conversion when DbMapping is not available
     */
    public function testHydrateFallbackToSnakeCaseConversion(): void
    {
        $data = [
            'id' => '123',
            'username' => 'JohnDoe',
            'account_balance' => '150.75',
            'blocked_at_date_time' => '2023-10-01 12:00:00',
        ];

        $hydratedEntity = new EntityHydrator()->hydrate($data, User::class);

        $this->assertSame(123, $hydratedEntity->getId());
        $this->assertSame('JohnDoe', $hydratedEntity->getUsername());
        $this->assertSame(150.75, $hydratedEntity->getAccountBalance());
        $this->assertInstanceOf(DateTime::class, $hydratedEntity->getBlockedAtDateTime());
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
