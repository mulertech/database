<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Scheduler;

/**
 * Manages entity scheduling for database operations
 */
class EntityScheduler
{
    /** @var array<object> */
    private array $scheduledInsertions = [];

    /** @var array<object> */
    private array $scheduledUpdates = [];

    /** @var array<object> */
    private array $scheduledDeletions = [];

    public function scheduleForInsertion(object $entity): void
    {
        if (!in_array($entity, $this->scheduledInsertions, true)) {
            $this->scheduledInsertions[] = $entity;
        }
    }

    public function scheduleForUpdate(object $entity): void
    {
        if (!in_array($entity, $this->scheduledUpdates, true)) {
            $this->scheduledUpdates[] = $entity;
        }
    }

    public function scheduleForDeletion(object $entity): void
    {
        if (!in_array($entity, $this->scheduledDeletions, true)) {
            $this->scheduledDeletions[] = $entity;
        }
    }

    public function isScheduledForInsertion(object $entity): bool
    {
        return in_array($entity, $this->scheduledInsertions, true);
    }

    public function isScheduledForUpdate(object $entity): bool
    {
        return in_array($entity, $this->scheduledUpdates, true);
    }

    public function isScheduledForDeletion(object $entity): bool
    {
        return in_array($entity, $this->scheduledDeletions, true);
    }

    public function removeFromSchedule(object $entity, string $scheduleType): void
    {
        $property = 'scheduled' . ucfirst($scheduleType);

        if (!property_exists($this, $property)) {
            return;
        }

        $schedule = &$this->$property;
        $key = array_search($entity, $schedule, true);

        if ($key !== false) {
            unset($schedule[$key]);
            $schedule = array_values($schedule);
        }
    }

    public function removeFromAllSchedules(object $entity): void
    {
        $this->removeFromSchedule($entity, 'insertions');
        $this->removeFromSchedule($entity, 'updates');
        $this->removeFromSchedule($entity, 'deletions');
    }

    /**
     * @return array<object>
     */
    public function getScheduledInsertions(): array
    {
        return $this->scheduledInsertions;
    }

    /**
     * @return array<object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->scheduledUpdates;
    }

    /**
     * @return array<object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->scheduledDeletions;
    }

    public function hasPendingSchedules(): bool
    {
        return !empty($this->scheduledInsertions) ||
               !empty($this->scheduledUpdates) ||
               !empty($this->scheduledDeletions);
    }

    public function clear(): void
    {
        $this->scheduledInsertions = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletions = [];
    }

    /**
     * @return array{insertions: int, updates: int, deletions: int}
     */
    public function getStatistics(): array
    {
        return [
            'insertions' => count($this->scheduledInsertions),
            'updates' => count($this->scheduledUpdates),
            'deletions' => count($this->scheduledDeletions),
        ];
    }
}
