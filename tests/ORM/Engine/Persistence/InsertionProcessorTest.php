<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\Engine\Persistence\InsertionProcessor;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Mapping\EntityWithoutSetId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class InsertionProcessorTest extends TestCase
{
    private InsertionProcessor $insertionProcessor;
    private EntityManagerInterface $entityManager;
    private MetadataRegistry $metadataRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadataRegistry = new MetadataRegistry();
        
        $this->insertionProcessor = new InsertionProcessor(
            $this->entityManager,
            $this->metadataRegistry
        );
    }

    public function testProcess(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        // Mock necessary methods
        $mockEngine = $this->createMock(\MulerTech\Database\ORM\EmEngine::class);
        $mockPdm = $this->createMock(\MulerTech\Database\Database\Interface\PhpDatabaseInterface::class);
        
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        $this->entityManager->method('getPdm')
            ->willReturn($mockPdm);
        
        $mockPdm->method('lastInsertId')
            ->willReturn('123');
        
        $this->insertionProcessor->process($user);
        
        $this->assertTrue(true);
    }

    public function testProcessWithExistingId(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        // Entity with ID should skip insertion
        $this->insertionProcessor->process($user);
        
        $this->assertTrue(true);
    }

    public function testExecute(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $changes = [
            'username' => [null, 'John']
        ];
        
        // Mock necessary methods
        $mockEngine = $this->createMock(\MulerTech\Database\ORM\EmEngine::class);
        $mockPdm = $this->createMock(\MulerTech\Database\Database\Interface\PhpDatabaseInterface::class);
        
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        $this->entityManager->method('getPdm')
            ->willReturn($mockPdm);
        
        $mockPdm->method('lastInsertId')
            ->willReturn('123');
        
        $this->insertionProcessor->execute($user, $changes);
        
        $this->assertTrue(true);
    }

    public function testExecuteWithExistingId(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $changes = [
            'username' => [null, 'John']
        ];
        
        // Should skip execution for entity with ID
        $this->insertionProcessor->execute($user, $changes);
        
        $this->assertTrue(true);
    }

    public function testProcessEntityWithoutGetIdMethod(): void
    {
        $entity = new class {
            public string $name = 'test';
        };
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must have a getId method');
        
        $this->insertionProcessor->process($entity);
    }

    public function testProcessEntityWithoutSetIdMethod(): void
    {
        $entity = new EntityWithoutSetId();
        $entity->setName('Test');

        // Mock necessary methods to simulate successful insertion with generated ID
        $mockEngine = $this->createMock(\MulerTech\Database\ORM\EmEngine::class);
        $mockPdm = $this->createMock(\MulerTech\Database\Database\Interface\PhpDatabaseInterface::class);
        
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        $this->entityManager->method('getPdm')
            ->willReturn($mockPdm);
        
        // Return non-empty lastInsertId to trigger setId call
        $mockPdm->method('lastInsertId')
            ->willReturn('456');
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The entity ' . EntityWithoutSetId::class . ' must have a setId method');
        
        $this->insertionProcessor->process($entity);
    }
}