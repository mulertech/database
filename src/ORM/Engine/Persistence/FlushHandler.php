<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\ORM\State\StateManagerInterface;
use ReflectionException;
use RuntimeException;

/**
 * Handles the complex flush logic for persistence operations
 */
final class FlushHandler
{
    private const int MAX_FLUSH_DEPTH = 10;

    private int $flushDepth = 0;
    private bool $hasPostEventChanges = false;

    /**
     * @param StateManagerInterface $stateManager
     * @param ChangeSetManager $changeSetManager
     * @param RelationManager $relationManager
     */
    public function __construct(
        private readonly StateManagerInterface $stateManager,
        private readonly ChangeSetManager $changeSetManager,
        private readonly RelationManager $relationManager,
    ) {
    }

    /**
     * @param callable $insertionProcessor
     * @param callable $updateProcessor
     * @param callable $deletionProcessor
     * @return void
     * @throws ReflectionException
     */
    public function doFlush(
        callable $insertionProcessor,
        callable $updateProcessor,
        callable $deletionProcessor
    ): void {
        $this->flushDepth++;

        if ($this->flushDepth > self::MAX_FLUSH_DEPTH) {
            throw new RuntimeException('Maximum flush depth reached. Possible circular dependency.');
        }

        $this->prepareFlush();
        $this->processOperations($insertionProcessor, $updateProcessor, $deletionProcessor);
        $this->finalizeFlush();
    }

    /**
     * @throws ReflectionException
     */
    private function prepareFlush(): void
    {
        $this->changeSetManager->computeChangeSets();

        $this->relationManager->processRelationChanges();
    }

    /**
     * @throws ReflectionException
     */
    private function processOperations(
        callable $insertionProcessor,
        callable $updateProcessor,
        callable $deletionProcessor
    ): void {
        $insertions = $this->changeSetManager->getScheduledInsertions();
        $deletions = $this->getAllDeletions();

        // Process deletions first
        foreach ($deletions as $entity) {
            $deletionProcessor($entity);
        }

        // Process insertions
        foreach ($insertions as $entity) {
            $insertionProcessor($entity);
        }

        // CRITICAL: Process relation changes after insertions
        // This ensures that many-to-many relations are detected after entities have IDs
        $this->relationManager->processRelationChanges();

        // Process updates (including any new ones from relation changes)
        $updatedEntities = $this->changeSetManager->getScheduledUpdates();
        foreach ($updatedEntities as $entity) {
            $updateProcessor($entity);
        }

        $this->processAdditionalOperations($insertionProcessor, $deletionProcessor);
    }

    /**
     * @return array<object>
     */
    private function getAllDeletions(): array
    {
        $deletions = $this->changeSetManager->getScheduledDeletions();
        $scheduleDeletions = $this->stateManager->getScheduledDeletions();

        return array_unique(array_merge(
            array_values($deletions),
            array_values($scheduleDeletions)
        ), SORT_REGULAR);
    }

    /**
     * @throws ReflectionException
     */
    private function processAdditionalOperations(callable $insertionProcessor, callable $deletionProcessor): void
    {
        // Process new deletions
        $newDeletions = $this->stateManager->getScheduledDeletions();
        $allDeletions = $this->getAllDeletions();
        foreach ($newDeletions as $entity) {
            if (!in_array($entity, $allDeletions, true)) {
                $deletionProcessor($entity);
            }
        }

        // CRITICAL: Process relation flush IMMEDIATELY after insertions to create link entities
        // This ensures that many-to-many relations are persisted when entities have IDs
        $this->relationManager->flush();

        // Process any new insertions that were scheduled by relation flush (link entities)
        $newInsertions = $this->changeSetManager->getScheduledInsertions();
        if (!empty($newInsertions)) {
            foreach ($newInsertions as $entity) {
                $insertionProcessor($entity);
            }
            // Clear processed insertions
            $this->changeSetManager->clearProcessedChanges();
        }
    }

    private function finalizeFlush(): void
    {
        $this->changeSetManager->clearProcessedChanges();
        $this->stateManager->clear();

        if ($this->hasPostEventChanges && $this->flushDepth < 3) {
            $this->hasPostEventChanges = false;
            // Note: This would need to be handled by the calling PersistenceManager
        }
    }

    /**
     * @return void
     */
    public function markPostEventChanges(): void
    {
        $this->hasPostEventChanges = true;
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $this->flushDepth = 0;
        $this->hasPostEventChanges = false;
    }

    /**
     * @return int
     */
    public function getFlushDepth(): int
    {
        return $this->flushDepth;
    }
}
