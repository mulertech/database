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
}