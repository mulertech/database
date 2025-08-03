<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Scheduler;

use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class EntitySchedulerTest extends TestCase
{
    private EntityScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new EntityScheduler();
    }

    public function testScheduleForInsertion(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForInsertion($user);
        
        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
        self::assertFalse($this->scheduler->isScheduledForUpdate($user));
        self::assertFalse($this->scheduler->isScheduledForDeletion($user));
        
        $insertions = $this->scheduler->getScheduledInsertions();
        self::assertCount(1, $insertions);
        self::assertContains($user, $insertions);
    }

    public function testScheduleForUpdate(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForUpdate($user);
        
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
        self::assertTrue($this->scheduler->isScheduledForUpdate($user));
        self::assertFalse($this->scheduler->isScheduledForDeletion($user));
        
        $updates = $this->scheduler->getScheduledUpdates();
        self::assertCount(1, $updates);
        self::assertContains($user, $updates);
    }

    public function testScheduleForDeletion(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForDeletion($user);
        
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
        self::assertFalse($this->scheduler->isScheduledForUpdate($user));
        self::assertTrue($this->scheduler->isScheduledForDeletion($user));
        
        $deletions = $this->scheduler->getScheduledDeletions();
        self::assertCount(1, $deletions);
        self::assertContains($user, $deletions);
    }

    public function testMultipleEntitiesInsertion(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $this->scheduler->scheduleForInsertion($user1);
        $this->scheduler->scheduleForInsertion($user2);
        $this->scheduler->scheduleForInsertion($unit);
        
        $insertions = $this->scheduler->getScheduledInsertions();
        self::assertCount(3, $insertions);
        self::assertContains($user1, $insertions);
        self::assertContains($user2, $insertions);
        self::assertContains($unit, $insertions);
    }

    public function testNoDuplicateScheduling(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForInsertion($user);
        $this->scheduler->scheduleForInsertion($user);
        $this->scheduler->scheduleForInsertion($user);
        
        $insertions = $this->scheduler->getScheduledInsertions();
        self::assertCount(1, $insertions);
        self::assertContains($user, $insertions);
    }

    public function testUnscheduleFromInsertion(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForInsertion($user);
        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
        
        $this->scheduler->unscheduleFromInsertion($user);
        
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
        
        $insertions = $this->scheduler->getScheduledInsertions();
        self::assertEmpty($insertions);
    }

    public function testUnscheduleFromUpdate(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForUpdate($user);
        self::assertTrue($this->scheduler->isScheduledForUpdate($user));
        
        $this->scheduler->unscheduleFromUpdate($user);
        
        self::assertFalse($this->scheduler->isScheduledForUpdate($user));
        
        $updates = $this->scheduler->getScheduledUpdates();
        self::assertEmpty($updates);
    }

    public function testUnscheduleFromDeletion(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForDeletion($user);
        self::assertTrue($this->scheduler->isScheduledForDeletion($user));
        
        $this->scheduler->unscheduleFromDeletion($user);
        
        self::assertFalse($this->scheduler->isScheduledForDeletion($user));
        
        $deletions = $this->scheduler->getScheduledDeletions();
        self::assertEmpty($deletions);
    }

    public function testUnscheduleNonScheduledEntity(): void
    {
        $user = new User();
        
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
        
        $this->scheduler->unscheduleFromInsertion($user);
        
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
    }

    public function testClear(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $this->scheduler->scheduleForInsertion($user1);
        $this->scheduler->scheduleForUpdate($user2);
        $this->scheduler->scheduleForDeletion($unit);
        
        self::assertTrue($this->scheduler->isScheduledForInsertion($user1));
        self::assertTrue($this->scheduler->isScheduledForUpdate($user2));
        self::assertTrue($this->scheduler->isScheduledForDeletion($unit));
        
        $this->scheduler->clear();
        
        self::assertFalse($this->scheduler->isScheduledForInsertion($user1));
        self::assertFalse($this->scheduler->isScheduledForUpdate($user2));
        self::assertFalse($this->scheduler->isScheduledForDeletion($unit));
        
        self::assertEmpty($this->scheduler->getScheduledInsertions());
        self::assertEmpty($this->scheduler->getScheduledUpdates());
        self::assertEmpty($this->scheduler->getScheduledDeletions());
    }

    public function testHasScheduledEntities(): void
    {
        self::assertFalse($this->scheduler->hasScheduledEntities());
        
        $user = new User();
        $this->scheduler->scheduleForInsertion($user);
        
        self::assertTrue($this->scheduler->hasScheduledEntities());
        
        $this->scheduler->clear();
        
        self::assertFalse($this->scheduler->hasScheduledEntities());
    }

    public function testHasScheduledEntitiesWithDifferentOperations(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        self::assertFalse($this->scheduler->hasScheduledEntities());
        
        $this->scheduler->scheduleForUpdate($user1);
        self::assertTrue($this->scheduler->hasScheduledEntities());
        
        $this->scheduler->scheduleForDeletion($user2);
        self::assertTrue($this->scheduler->hasScheduledEntities());
        
        $this->scheduler->scheduleForInsertion($unit);
        self::assertTrue($this->scheduler->hasScheduledEntities());
        
        $this->scheduler->unscheduleFromUpdate($user1);
        $this->scheduler->unscheduleFromDeletion($user2);
        self::assertTrue($this->scheduler->hasScheduledEntities());
        
        $this->scheduler->unscheduleFromInsertion($unit);
        self::assertFalse($this->scheduler->hasScheduledEntities());
    }

    public function testGetAllScheduledEntities(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $this->scheduler->scheduleForInsertion($user1);
        $this->scheduler->scheduleForUpdate($user2);
        $this->scheduler->scheduleForDeletion($unit);
        
        $allEntities = $this->scheduler->getAllScheduledEntities();
        
        self::assertCount(3, $allEntities);
        self::assertContains($user1, $allEntities);
        self::assertContains($user2, $allEntities);
        self::assertContains($unit, $allEntities);
    }

    public function testGetAllScheduledEntitiesWithDuplicates(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForInsertion($user);
        $this->scheduler->scheduleForUpdate($user);
        
        $allEntities = $this->scheduler->getAllScheduledEntities();
        
        self::assertCount(1, $allEntities);
        self::assertContains($user, $allEntities);
    }

    public function testGetAllScheduledEntitiesEmpty(): void
    {
        $allEntities = $this->scheduler->getAllScheduledEntities();
        
        self::assertEmpty($allEntities);
    }

    public function testEntityCanBeInMultipleSchedules(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForInsertion($user);
        $this->scheduler->scheduleForUpdate($user);
        
        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
        self::assertTrue($this->scheduler->isScheduledForUpdate($user));
        self::assertFalse($this->scheduler->isScheduledForDeletion($user));
    }
}