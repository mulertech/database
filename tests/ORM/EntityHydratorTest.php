<?php

namespace MulerTech\Database\Tests\ORM;

use DateTime;
use MulerTech\Database\Mapping\ColumnType;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\ORM\EntityHydrator;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * Tests for the EntityHydrator class
 */
class EntityHydratorTest extends TestCase
{
    private EntityHydrator $hydrator;
    private DbMappingInterface $dbMappingMock;

    protected function setUp(): void
    {
        // Mock DbMappingInterface
        $this->dbMappingMock = $this->createMock(DbMappingInterface::class);
        $this->hydrator = new EntityHydrator($this->dbMappingMock);
    }

    /**
     * Test hydration with DbMapping and setters
     */
    public function testHydrateWithDbMappingAndSetters(): void
    {
        $data = [
            'id' => '42',
            'user_name' => 'Test User',
            'is_active' => '1',
            'account_balance' => '100.50',
        ];

        // Mock DbMapping behavior
        $this->dbMappingMock->method('getPropertiesColumns')
            ->willReturn([
                'id' => 'id',
                'userName' => 'user_name',
                'isActive' => 'is_active',
                'accountBalance' => 'account_balance',
            ]);

        $this->dbMappingMock->method('getColumnType')
            ->willReturnMap([
                ['TestEntity', 'id', ColumnType::INT],
                ['TestEntity', 'userName', ColumnType::VARCHAR],
                ['TestEntity', 'isActive', ColumnType::BOOLEAN],
                ['TestEntity', 'accountBalance', ColumnType::DECIMAL],
            ]);

        $entity = new class {
            private int $id;
            private string $userName;
            private bool $isActive;
            private float $accountBalance;

            public function setId(int $id): void
            {
                $this->id = $id;
            }

            public function setUserName(string $userName): void
            {
                $this->userName = $userName;
            }

            public function setIsActive(bool $isActive): void
            {
                $this->isActive = $isActive;
            }

            public function setAccountBalance(float $accountBalance): void
            {
                $this->accountBalance = $accountBalance;
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getUserName(): string
            {
                return $this->userName;
            }

            public function getIsActive(): bool
            {
                return $this->isActive;
            }

            public function getAccountBalance(): float
            {
                return $this->accountBalance;
            }
        };

        $hydratedEntity = $this->hydrator->hydrate($data, $entity::class);

        $this->assertSame(42, $hydratedEntity->getId());
        $this->assertSame('Test User', $hydratedEntity->getUserName());
        $this->assertTrue($hydratedEntity->getIsActive());
        $this->assertSame(100.50, $hydratedEntity->getAccountBalance());
    }

    /**
     * Test fallback to snake_case to camelCase conversion when DbMapping is not available
     */
    public function testHydrateFallbackToSnakeCaseConversion(): void
    {
        $data = [
            'user_id' => '123',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $entity = new class {
            private int $userId;
            private string $firstName;
            private string $lastName;

            public function setUserId(int $userId): void
            {
                $this->userId = $userId;
            }

            public function setFirstName(string $firstName): void
            {
                $this->firstName = $firstName;
            }

            public function setLastName(string $lastName): void
            {
                $this->lastName = $lastName;
            }

            public function getUserId(): int
            {
                return $this->userId;
            }

            public function getFirstName(): string
            {
                return $this->firstName;
            }

            public function getLastName(): string
            {
                return $this->lastName;
            }
        };

        $hydratedEntity = $this->hydrator->hydrate($data, $entity::class);

        $this->assertSame(123, $hydratedEntity->getUserId());
        $this->assertSame('John', $hydratedEntity->getFirstName());
        $this->assertSame('Doe', $hydratedEntity->getLastName());
    }

    /**
     * Test hydration with invalid column type in DbMapping
     */
    public function testHydrateWithInvalidColumnType(): void
    {
        $data = [
            'id' => 'invalid-int',
        ];

        $this->dbMappingMock->method('getPropertiesColumns')
            ->willReturn(['id' => 'id']);

        $this->dbMappingMock->method('getColumnType')
            ->willReturn(ColumnType::INT);

        $entity = new class {
            private int $id;

            public function setId(int $id): void
            {
                $this->id = $id;
            }

            public function getId(): int
            {
                return $this->id;
            }
        };

        $hydratedEntity = $this->hydrator->hydrate($data, $entity::class);

        // The invalid value should be coerced to 0
        $this->assertSame(0, $hydratedEntity->getId());
    }

    /**
     * Test hydration with missing setter method
     */
    public function testHydrateWithMissingSetter(): void
    {
        $data = [
            'id' => '42',
            'name' => 'Test Entity',
        ];

        $entity = new class {
            private int $id;
            private ?string $name = null;

            public function setId(int $id): void
            {
                $this->id = $id;
            }
            public function getId(): int
            {
                return $this->id;
            }

            // No setter for 'name'
            public function getName(): ?string
            {
                return $this->name;
            }
        };

        $hydratedEntity = $this->hydrator->hydrate($data, $entity::class);

        $this->assertSame(42, $hydratedEntity->getId());
        // 'name' should be ignored since no setter exists
        $this->assertNull($hydratedEntity->getName());
    }

    /**
     * Test hydration with null values
     */
    public function testHydrateWithNullValues(): void
    {
        $data = [
            'id' => null,
            'name' => null,
        ];

        $this->dbMappingMock->method('getPropertiesColumns')
            ->willReturn(['id' => 'id', 'name' => 'name']);

        $this->dbMappingMock->method('getColumnType')
            ->willReturnMap([
                ['TestEntity', 'id', ColumnType::INT],
                ['TestEntity', 'name', ColumnType::VARCHAR],
            ]);

        $entity = new class {
            private ?int $id = 0;
            private ?string $name = '';

            public function setId(?int $id): void
            {
                $this->id = $id;
            }

            public function setName(?string $name): void
            {
                $this->name = $name;
            }

            public function getId(): ?int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }
        };

        $hydratedEntity = $this->hydrator->hydrate($data, $entity::class);

        $this->assertNull($hydratedEntity->getId());
        $this->assertNull($hydratedEntity->getName());
    }
}
