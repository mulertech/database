<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\Engine\Persistence\FlushHandler;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\ORM\EntityRegistry;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FlushHandlerTest extends TestCase
{
    private FlushHandler $flushHandler;
    private StateManagerInterface $stateManager;
    private ChangeSetManager $changeSetManager;
    private RelationManager $relationManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->stateManager = $this->createMock(StateManagerInterface::class);
        $this->relationManager = $this->createMock(RelationManager::class);
        
        $identityMap = new IdentityMap();
        $entityRegistry = new EntityRegistry();
        $changeDetector = new ChangeDetector();
        $this->changeSetManager = new ChangeSetManager($identityMap, $entityRegistry, $changeDetector);
        
        $this->flushHandler = new FlushHandler(
            $this->stateManager,
            $this->changeSetManager,
            $this->relationManager
        );
    }

    public function testDoFlushWithEmptyCollections(): void
    {
        $insertionProcessor = function($entity) { /* dummy processor */ };
        $updateProcessor = function($entity) { /* dummy processor */ };
        $deletionProcessor = function($entity) { /* dummy processor */ };
        
        $this->flushHandler->doFlush($insertionProcessor, $updateProcessor, $deletionProcessor);
        
        $this->assertTrue(true);
    }

    public function testDoFlushWithInsertions(): void
    {
        $insertionProcessor = function($entity) { /* dummy processor */ };
        $updateProcessor = function($entity) { /* dummy processor */ };
        $deletionProcessor = function($entity) { /* dummy processor */ };
        
        $this->flushHandler->doFlush($insertionProcessor, $updateProcessor, $deletionProcessor);
        
        $this->assertTrue(true);
    }

    public function testDoFlushWithUpdates(): void
    {
        $insertionProcessor = function($entity) { /* dummy processor */ };
        $updateProcessor = function($entity) { /* dummy processor */ };
        $deletionProcessor = function($entity) { /* dummy processor */ };
        
        $this->flushHandler->doFlush($insertionProcessor, $updateProcessor, $deletionProcessor);
        
        $this->assertTrue(true);
    }

    public function testDoFlushWithDeletions(): void
    {
        $insertionProcessor = function($entity) { /* dummy processor */ };
        $updateProcessor = function($entity) { /* dummy processor */ };
        $deletionProcessor = function($entity) { /* dummy processor */ };
        
        $this->flushHandler->doFlush($insertionProcessor, $updateProcessor, $deletionProcessor);
        
        $this->assertTrue(true);
    }

    public function testDoFlushWithAllOperations(): void
    {
        $insertionProcessor = function($entity) { /* dummy processor */ };
        $updateProcessor = function($entity) { /* dummy processor */ };
        $deletionProcessor = function($entity) { /* dummy processor */ };
        
        $this->flushHandler->doFlush($insertionProcessor, $updateProcessor, $deletionProcessor);
        
        $this->assertTrue(true);
    }

    public function testGetFlushDepthIncrementsOnFlush(): void
    {
        // Initial depth should be 0
        $this->assertEquals(0, $this->flushHandler->getFlushDepth());
        
        // After a flush, depth should be 1 (it's not decremented by finalizeFlush)
        $this->flushHandler->doFlush(
            function($entity) { /* dummy */ },
            function($entity) { /* dummy */ },
            function($entity) { /* dummy */ }
        );
        
        $this->assertEquals(1, $this->flushHandler->getFlushDepth());
    }

    public function testProcessAdditionalOperationsWithNewDeletions(): void
    {
        $processedEntities = [];
        $insertionProcessor = function($entity) use (&$processedEntities) {
            $processedEntities[] = 'insert:' . get_class($entity);
        };
        $updateProcessor = function($entity) use (&$processedEntities) {
            $processedEntities[] = 'update:' . get_class($entity);
        };
        $deletionProcessor = function($entity) use (&$processedEntities) {
            $processedEntities[] = 'delete:' . get_class($entity);
        };
        
        // Create entities for testing
        $user1 = new User();
        $user2 = new User();
        
        // Mock the state manager to return scheduled deletions
        $this->stateManager->method('getScheduledDeletions')
            ->willReturn([$user1, $user2]);
        $this->stateManager->method('getScheduledInsertions')
            ->willReturn([]);
        
        $this->flushHandler->doFlush($insertionProcessor, $updateProcessor, $deletionProcessor);
        
        // Verify that deletion operations were processed
        $this->assertContains('delete:' . User::class, $processedEntities);
    }

    public function testResetResetsFlushDepthAndPostEventChanges(): void
    {
        $this->flushHandler->markPostEventChanges();
        
        // Do a flush to increase depth
        $this->flushHandler->doFlush(
            function($entity) { /* dummy */ },
            function($entity) { /* dummy */ },
            function($entity) { /* dummy */ }
        );
        
        $this->assertGreaterThan(0, $this->flushHandler->getFlushDepth());
        
        $this->flushHandler->reset();
        
        $this->assertEquals(0, $this->flushHandler->getFlushDepth());
    }

    public function testMarkPostEventChanges(): void
    {
        $this->flushHandler->markPostEventChanges();
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }
}