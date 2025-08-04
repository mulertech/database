<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\ORM\Engine\Persistence\DeletionProcessor;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DeletionProcessorTest extends TestCase
{
    private DeletionProcessor $deletionProcessor;
    private EntityManagerInterface $entityManager;
    private MetadataCache $metadataCache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadataCache = new MetadataCache();
        
        $this->deletionProcessor = new DeletionProcessor(
            $this->entityManager,
            $this->metadataCache
        );
    }

    public function testProcess(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        // Mock necessary methods for the deletion process
        $this->entityManager->method('getEmEngine')
            ->willReturn($this->createMock(\MulerTech\Database\ORM\EmEngine::class));
        
        $this->deletionProcessor->process($user);
        
        $this->assertTrue(true);
    }

    public function testProcessWithoutId(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->entityManager->method('getEmEngine')
            ->willReturn($this->createMock(\MulerTech\Database\ORM\EmEngine::class));
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete entity');
        
        $this->deletionProcessor->process($user);
    }

    public function testExecute(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $mockEngine = $this->createMock(\MulerTech\Database\ORM\EmEngine::class);
        $this->entityManager->method('getEmEngine')
            ->willReturn($mockEngine);
        
        $this->deletionProcessor->execute($user);
        
        $this->assertTrue(true);
    }
}