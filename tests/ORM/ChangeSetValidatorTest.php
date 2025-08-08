<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\ChangeSetValidator;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\PropertyChange;
use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

class ChangeSetValidatorTest extends TestCase
{
    private ChangeSetValidator $validator;
    private IdentityMap $identityMap;
    private EntityScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identityMap = new IdentityMap();
        $this->validator = new ChangeSetValidator($this->identityMap);
        $this->scheduler = new EntityScheduler();
    }

    public function testShouldSkipInsertionWithNullId(): void
    {
        $result = $this->validator->shouldSkipInsertion(null, null);
        
        self::assertFalse($result);
    }

    public function testShouldSkipInsertionWithIdButNoMetadata(): void
    {
        $result = $this->validator->shouldSkipInsertion(123, null);
        
        self::assertTrue($result);
    }

    public function testShouldSkipInsertionWithIdAndNewMetadata(): void
    {
        $metadata = new EntityState(
            User::class,
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $result = $this->validator->shouldSkipInsertion(123, $metadata);
        
        self::assertTrue($result);
    }

    public function testShouldSkipInsertionWithIdAndManagedMetadata(): void
    {
        $metadata = new EntityState(
            User::class,
            EntityLifecycleState::MANAGED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $result = $this->validator->shouldSkipInsertion(123, $metadata);
        
        self::assertTrue($result);
    }

    public function testShouldSkipInsertionWithIdAndRemovedMetadata(): void
    {
        $metadata = new EntityState(
            User::class,
            EntityLifecycleState::REMOVED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $result = $this->validator->shouldSkipInsertion(123, $metadata);
        
        self::assertTrue($result);
    }

    public function testShouldSkipInsertionWithIdAndDetachedMetadata(): void
    {
        $metadata = new EntityState(
            User::class,
            EntityLifecycleState::DETACHED,
            ['username' => 'John'],
            new \DateTimeImmutable()
        );
        
        $result = $this->validator->shouldSkipInsertion(123, $metadata);
        
        self::assertTrue($result);
    }

    public function testShouldSkipInsertionWithStringId(): void
    {
        $result = $this->validator->shouldSkipInsertion('uuid-123', null);
        
        self::assertTrue($result);
    }

    public function testCanScheduleUpdateWithManagedEntity(): void
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
        
        $result = $this->validator->canScheduleUpdate($user, $this->scheduler);
        
        self::assertTrue($result);
    }

    public function testCanScheduleUpdateWithNewEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            User::class,
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $result = $this->validator->canScheduleUpdate($user, $this->scheduler);
        
        self::assertFalse($result);
    }

    public function testCanScheduleUpdateWithUnmanagedEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $result = $this->validator->canScheduleUpdate($user, $this->scheduler);
        
        self::assertFalse($result);
    }

    public function testCanScheduleUpdateWithScheduledInsertion(): void
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
        $this->scheduler->scheduleForInsertion($user);
        
        $result = $this->validator->canScheduleUpdate($user, $this->scheduler);
        
        self::assertFalse($result);
    }

    public function testCanScheduleUpdateWithScheduledDeletion(): void
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
        
        $result = $this->validator->canScheduleUpdate($user, $this->scheduler);
        
        self::assertFalse($result);
    }

    public function testCanScheduleUpdateWithAlreadyScheduledUpdate(): void
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
        
        $result = $this->validator->canScheduleUpdate($user, $this->scheduler);
        
        self::assertFalse($result);
    }

    public function testCanScheduleDeletionWithManagedEntity(): void
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
        
        $result = $this->validator->canScheduleDeletion($user, $this->scheduler);
        
        self::assertTrue($result);
    }

    public function testCanScheduleDeletionWithNewEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $entityState = new EntityState(
            User::class,
            EntityLifecycleState::NEW,
            [],
            new \DateTimeImmutable()
        );
        
        $this->identityMap->add($user, 1, $entityState);
        
        $result = $this->validator->canScheduleDeletion($user, $this->scheduler);
        
        self::assertFalse($result);
    }

    public function testCanScheduleDeletionWithAlreadyScheduledDeletion(): void
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
        
        $result = $this->validator->canScheduleDeletion($user, $this->scheduler);
        
        self::assertFalse($result);
    }

    public function testValidateChangeSet(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $propertyChange = new PropertyChange('username', null, 'John');
        $changeSet = new ChangeSet($user::class, ['username' => $propertyChange]);
        
        $result = $this->validator->validateChangeSet($changeSet);
        
        self::assertTrue($result);
    }

    public function testValidateChangeSetWithInvalidChanges(): void
    {
        $user = new User();
        
        // Empty change set should be invalid
        $changeSet = new ChangeSet($user::class, []);
        
        $result = $this->validator->validateChangeSet($changeSet);
        
        self::assertFalse($result);
    }

    public function testReadonlyClass(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    public function testValidatorDependencies(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $constructor = $reflection->getConstructor();
        
        self::assertNotNull($constructor);
        
        $parameters = $constructor->getParameters();
        self::assertCount(1, $parameters);
        self::assertEquals('identityMap', $parameters[0]->getName());
    }
}

