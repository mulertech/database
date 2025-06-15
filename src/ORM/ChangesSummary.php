<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\ORM\ChangeTracking\EntityChange;

/**
 * Summary of all changes across entities
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
final readonly class ChangesSummary
{
    /**
     * @param array<object> $insertions
     * @param array<EntityChange> $updates
     * @param array<object> $deletions
     */
    public function __construct(
        public array $insertions,
        public array $updates,
        public array $deletions
    ) {
    }

    /**
     * @return bool
     */
    public function hasChanges(): bool
    {
        return !empty($this->insertions) || !empty($this->updates) || !empty($this->deletions);
    }

    /**
     * @return int
     */
    public function getTotalChangeCount(): int
    {
        return count($this->insertions) + count($this->updates) + count($this->deletions);
    }

    /**
     * @return array{insertions: int, updates: int, deletions: int, total: int}
     */
    public function getStatistics(): array
    {
        return [
            'insertions' => count($this->insertions),
            'updates' => count($this->updates),
            'deletions' => count($this->deletions),
            'total' => $this->getTotalChangeCount(),
        ];
    }
}
