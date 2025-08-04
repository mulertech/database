<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Scheduler;

use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
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

    public function testRemoveFromScheduleInsertion(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForInsertion($user);
        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
        
        $this->scheduler->removeFromSchedule($user, 'insertions');

        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
        
        $insertions = $this->scheduler->getScheduledInsertions();
        self::assertEmpty($insertions);
    }

    public function testRemoveFromScheduleUpdate(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForUpdate($user);
        self::assertTrue($this->scheduler->isScheduledForUpdate($user));
        
        $this->scheduler->removeFromSchedule($user, 'updates');

        self::assertFalse($this->scheduler->isScheduledForUpdate($user));
        
        $updates = $this->scheduler->getScheduledUpdates();
        self::assertEmpty($updates);
    }

    public function testRemoveFromScheduleDeletion(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForDeletion($user);
        self::assertTrue($this->scheduler->isScheduledForDeletion($user));
        
        $this->scheduler->removeFromSchedule($user, 'deletions');

        self::assertFalse($this->scheduler->isScheduledForDeletion($user));
        
        $deletions = $this->scheduler->getScheduledDeletions();
        self::assertEmpty($deletions);
    }

    public function testRemoveFromScheduleNonScheduledEntity(): void
    {
        $user = new User();
        
        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
        
        $this->scheduler->removeFromSchedule($user, 'insertions');

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

    public function testHasPendingSchedules(): void
    {
        self::assertFalse($this->scheduler->hasPendingSchedules());

        $user = new User();
        $this->scheduler->scheduleForInsertion($user);
        
        self::assertTrue($this->scheduler->hasPendingSchedules());

        $this->scheduler->clear();
        
        self::assertFalse($this->scheduler->hasPendingSchedules());
    }

    public function testHasPendingSchedulesWithDifferentOperations(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        self::assertFalse($this->scheduler->hasPendingSchedules());

        $this->scheduler->scheduleForUpdate($user1);
        self::assertTrue($this->scheduler->hasPendingSchedules());

        $this->scheduler->scheduleForDeletion($user2);
        self::assertTrue($this->scheduler->hasPendingSchedules());

        $this->scheduler->scheduleForInsertion($unit);
        self::assertTrue($this->scheduler->hasPendingSchedules());

        $this->scheduler->removeFromSchedule($user1, 'updates');
        $this->scheduler->removeFromSchedule($user2, 'deletions');
        self::assertTrue($this->scheduler->hasPendingSchedules());

        $this->scheduler->removeFromSchedule($unit, 'insertions');
        self::assertFalse($this->scheduler->hasPendingSchedules());
    }

    public function testGetStatistics(): void
    {
        $user1 = new User();
        $user2 = new User();
        $unit = new Unit();
        
        $this->scheduler->scheduleForInsertion($user1);
        $this->scheduler->scheduleForUpdate($user2);
        $this->scheduler->scheduleForDeletion($unit);
        
        $stats = $this->scheduler->getStatistics();

        self::assertEquals(1, $stats['insertions']);
        self::assertEquals(1, $stats['updates']);
        self::assertEquals(1, $stats['deletions']);
    }

    public function testGetStatisticsWithDuplicates(): void
    {
        $user = new User();
        
        $this->scheduler->scheduleForInsertion($user);
        $this->scheduler->scheduleForUpdate($user);
        
        $stats = $this->scheduler->getStatistics();

        self::assertEquals(1, $stats['insertions']);
        self::assertEquals(1, $stats['updates']);
        self::assertEquals(0, $stats['deletions']);
    }

    public function testGetStatisticsEmpty(): void
    {
        $stats = $this->scheduler->getStatistics();

        self::assertEquals(0, $stats['insertions']);
        self::assertEquals(0, $stats['updates']);
        self::assertEquals(0, $stats['deletions']);
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

    public function testRemoveFromAllSchedules(): void
    {
        $user = new User();

        $this->scheduler->scheduleForInsertion($user);
        $this->scheduler->scheduleForUpdate($user);
        $this->scheduler->scheduleForDeletion($user);

        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
        self::assertTrue($this->scheduler->isScheduledForUpdate($user));
        self::assertTrue($this->scheduler->isScheduledForDeletion($user));

        $this->scheduler->removeFromAllSchedules($user);

        self::assertFalse($this->scheduler->isScheduledForInsertion($user));
        self::assertFalse($this->scheduler->isScheduledForUpdate($user));
        self::assertFalse($this->scheduler->isScheduledForDeletion($user));
    }

    public function testRemoveFromScheduleInvalidType(): void
    {
        $user = new User();

        $this->scheduler->scheduleForInsertion($user);

        // Test avec un type de schedule invalide
        $this->scheduler->removeFromSchedule($user, 'invalid');

        // L'entité doit toujours être présente car le type est invalide
        self::assertTrue($this->scheduler->isScheduledForInsertion($user));
    }
}