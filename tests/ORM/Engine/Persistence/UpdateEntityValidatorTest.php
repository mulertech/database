<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\Engine\Persistence\UpdateEntityValidator;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UpdateEntityValidatorTest extends TestCase
{
    private UpdateEntityValidator $validator;
    private EntityManagerInterface $entityManager;
    private MetadataRegistry $metadataRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadataRegistry = new MetadataRegistry();
        
        $this->validator = new UpdateEntityValidator(
            $this->entityManager,
            $this->metadataRegistry
        );
    }

    public function testValidateForUpdate(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        // Mock database methods
        $mockPdm = $this->createMock(\MulerTech\Database\Database\Interface\PhpDatabaseInterface::class);
        $mockStatement = $this->createMock(\MulerTech\Database\Database\Interface\Statement::class);
        
        $this->entityManager->method('getPdm')
            ->willReturn($mockPdm);
        
        $mockPdm->method('prepare')
            ->willReturn($mockStatement);
        
        $mockStatement->method('fetchColumn')
            ->willReturn(1); // Entity exists
        
        $result = $this->validator->validateForUpdate($user);
        
        $this->assertTrue($result);
    }

    public function testValidateForUpdateWithoutId(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $result = $this->validator->validateForUpdate($user);
        
        $this->assertFalse($result);
    }

    public function testGetEntityId(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $id = $this->validator->getEntityId($user);
        
        $this->assertEquals(123, $id);
    }

    public function testGetEntityIdWithoutId(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $id = $this->validator->getEntityId($user);
        
        $this->assertNull($id);
    }

    public function testGetEntityIdWithoutGetIdMethod(): void
    {
        $entity = new class {
            public string $name = 'test';
        };
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must have a getId method');
        
        $this->validator->getEntityId($entity);
    }

    public function testValidateForUpdateWithStringId(): void
    {
        $user = new User();
        $user->setId(456);
        $user->setUsername('John');
        
        // Mock database methods
        $mockPdm = $this->createMock(\MulerTech\Database\Database\Interface\PhpDatabaseInterface::class);
        $mockStatement = $this->createMock(\MulerTech\Database\Database\Interface\Statement::class);
        
        $this->entityManager->method('getPdm')
            ->willReturn($mockPdm);
        
        $mockPdm->method('prepare')
            ->willReturn($mockStatement);
        
        $mockStatement->method('fetchColumn')
            ->willReturn(1); // Entity exists
        
        $result = $this->validator->validateForUpdate($user);
        
        $this->assertTrue($result);
    }

    public function testValidateForUpdateEntityNotInDatabase(): void
    {
        $user = new User();
        $user->setId(999);
        $user->setUsername('John');
        
        // Mock database methods
        $mockPdm = $this->createMock(\MulerTech\Database\Database\Interface\PhpDatabaseInterface::class);
        $mockStatement = $this->createMock(\MulerTech\Database\Database\Interface\Statement::class);
        
        $this->entityManager->method('getPdm')
            ->willReturn($mockPdm);
        
        $mockPdm->method('prepare')
            ->willReturn($mockStatement);
        
        $mockStatement->method('fetchColumn')
            ->willReturn(0); // Entity doesn't exist
        
        $result = $this->validator->validateForUpdate($user);
        
        $this->assertFalse($result);
    }

    public function testEntityExistsInDatabaseWithNullId(): void
    {
        // Create a mock entity that will return null for getId() during entityExistsInDatabase check
        $mockEntity = $this->createMock(User::class);
        $mockEntity->method('getId')
            ->willReturn(null);
        
        // Use reflection to call the private method directly
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('entityExistsInDatabase');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->validator, $mockEntity);
        
        $this->assertFalse($result);
    }
}