<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetOperationHandler;
use MulerTech\Database\ORM\ChangeSetValidator;
use MulerTech\Database\ORM\EntityRegistry;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\EntityStateManager;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class ChangeSetOperationHandlerTest extends TestCase
{
    private ChangeSetOperationHandler $handler;
    private IdentityMap $identityMap;
    private EntityRegistry $registry;
    private ChangeDetector $changeDetector;
    private ChangeSetValidator $validator;
    private EntityScheduler $scheduler;
    private EntityStateManager $stateManager;
    private EntityProcessor $entityProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $metadataRegistry = new MetadataRegistry();
        $this->identityMap = new IdentityMap($metadataRegistry);
        $this->registry = new EntityRegistry();
        $this->changeDetector = new ChangeDetector($metadataRegistry);
        $this->validator = new ChangeSetValidator($this->identityMap);
        
        $this->handler = new ChangeSetOperationHandler(
            $this->identityMap,
            $this->registry,
            $this->changeDetector,
            $this->validator
        );
        
        $this->scheduler = new EntityScheduler();
        $this->stateManager = new EntityStateManager($this->identityMap);
        $this->entityProcessor = new EntityProcessor($this->changeDetector, $this->identityMap, $metadataRegistry);
    }

    public function testHandleInsertionScheduling(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->handler->handleInsertionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
    }

    public function testHandleInsertionSchedulingAlreadyScheduled(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->scheduler->scheduleForInsertion($user);
        
        $this->handler->handleInsertionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        $insertions = $this->scheduler->getScheduledInsertions();
        $userCount = array_filter($insertions, fn($entity) => $entity === $user);
        
        self::assertCount(1, $userCount);
    }

    public function testHandleInsertionSchedulingWithExistingId(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $this->handler->handleInsertionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata->state);
    }

    public function testHandleUpdateScheduling(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            User::class,
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $this->handler->handleUpdateScheduling($user, $this->scheduler);
        
        self::assertTrue($this->scheduler->isScheduledForUpdate($user));
    }

    public function testHandleUpdateSchedulingNewEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->handler->handleUpdateScheduling($user, $this->scheduler);
        
        self::assertFalse($this->scheduler->isScheduledForUpdate($user));
    }

    public function testHandleUpdateSchedulingAlreadyScheduled(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            User::class,
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        $this->scheduler->scheduleForUpdate($user);
        
        $this->handler->handleUpdateScheduling($user, $this->scheduler);
        
        $updates = $this->scheduler->getScheduledUpdates();
        $userCount = array_filter($updates, fn($entity) => $entity === $user);
        
        self::assertCount(1, $userCount);
    }

    public function testHandleDeletionScheduling(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            User::class,
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $this->handler->handleDeletionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager
        );
        
        self::assertTrue($this->scheduler->isScheduledForDeletion($user));
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::REMOVED, $metadata->state);
    }

    public function testHandleDeletionSchedulingNewEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->handler->handleDeletionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager
        );
        
        self::assertFalse($this->scheduler->isScheduledForDeletion($user));
    }

    public function testHandleDeletionSchedulingAlreadyScheduled(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            User::class,
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        $this->scheduler->scheduleForDeletion($user);
        
        $this->handler->handleDeletionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager
        );
        
        $deletions = $this->scheduler->getScheduledDeletions();
        $userCount = array_filter($deletions, fn($entity) => $entity === $user);
        
        self::assertCount(1, $userCount);
    }

    public function testHandleDetachment(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            User::class,
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        $this->registry->register($user);
        $this->scheduler->scheduleForUpdate($user);
        
        $this->handler->handleDetachment(
            $user,
            $this->scheduler,
            $this->stateManager
        );
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::DETACHED, $metadata->state);
        
        self::assertFalse($this->registry->isRegistered($user));
        self::assertFalse($this->scheduler->isScheduledForUpdate($user));
    }

    public function testHandleDetachmentWithScheduledInsertion(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->scheduler->scheduleForInsertion($user);
        $this->registry->register($user);
        
        $this->handler->handleDetachment(
            $user,
            $this->scheduler,
            $this->stateManager
        );
        
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
        self::assertFalse($this->registry->isRegistered($user));
    }

    public function testHandleDetachmentWithScheduledDeletion(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            User::class,
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        $this->scheduler->scheduleForDeletion($user);
        $this->registry->register($user);
        
        $this->handler->handleDetachment(
            $user,
            $this->scheduler,
            $this->stateManager
        );
        
        self::assertFalse($this->scheduler->isScheduledForDeletion($user));
        self::assertFalse($this->registry->isRegistered($user));
    }

    public function testHandleComplexEntityWithRelations(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        
        $this->handler->handleInsertionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
    }

    public function testReadonlyClass(): void
    {
        $reflection = new \ReflectionClass($this->handler);
        
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    public function testHandlerWithMultipleEntities(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user2 = new User();
        $user2->setUsername('Jane');
        
        $this->handler->handleInsertionScheduling(
            $user1,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        $this->handler->handleInsertionScheduling(
            $user2,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        self::assertTrue($this->scheduler->isScheduledForInsertion($user1));
        self::assertTrue($this->scheduler->isScheduledForInsertion($user2));
        
        $insertions = $this->scheduler->getScheduledInsertions();
        self::assertCount(2, $insertions);
    }

    public function testHandleInsertionSchedulingSkipsInsertion(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setUsername('John');
        
        // Create metadata with MANAGED state to trigger shouldSkipInsertion
        $entityState = new EntityState(
            className: User::class,
            state: EntityLifecycleState::MANAGED,
            originalData: ['username' => 'John'],
            lastModified: new \DateTimeImmutable()
        );
        
        // Add entity to identity map with metadata
        $this->identityMap->add($user, 1, $entityState);
        
        // This should trigger the echo statement at line 65
        $this->handler->handleInsertionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        // Verify entity was not scheduled for insertion due to skip condition
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
    }

    public function testHandleEntityLifecycleStateForInsertionWithNonNewState(): void
    {
        // Create an entity without an ID to avoid the shouldSkipInsertion condition
        $user = new User();
        $user->setUsername('TestUser');
        // Don't set ID so entityId will be null
        
        // Create metadata with MANAGED state (not NEW) 
        $entityState = new EntityState(
            className: User::class,
            state: EntityLifecycleState::MANAGED,
            originalData: ['username' => 'TestUser'],
            lastModified: new \DateTimeImmutable()
        );
        
        // Add entity to identity map with MANAGED state
        // Use null as ID since entity has no ID
        $this->identityMap->add($user, null, $entityState);
        
        // This should trigger handleEntityLifecycleStateForInsertion
        // with metadata that has state MANAGED (not NEW), triggering echo at line 159
        $this->handler->handleInsertionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        // Verify entity was scheduled for insertion
        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
    }

    public function testHandleInsertionSchedulingWithEntityIdAndNullMetadata(): void
    {
        $user = new User();
        $user->setId(4);
        $user->setUsername('TestUser');
        
        // Don't add to identity map, so metadata will be null
        // but entity has ID, so shouldSkipInsertion should return true
        
        // This should trigger the echo statement at line 65
        $this->handler->handleInsertionScheduling(
            $user,
            $this->scheduler,
            $this->stateManager,
            $this->entityProcessor
        );
        
        // Since shouldSkipInsertion returns true for entities with ID,
        // the entity should not be scheduled
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
    }
}