<?php

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\Engine\EntityState\EntityStateManager;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\EventManager\EventManager;
use MulerTech\Database\ORM\EntityMetadata;
use MulerTech\Database\ORM\State\EntityState;
use ReflectionException;

/**
 * Main manager for entity persistence
 *
 * @package MulerTech\Database\ORM\Engine\Persistence
 * @author Sébastien Muler
 */
class PersistenceManager
{
    /**
     * @var bool
     */
    private bool $isFlushInProgress = false;

    /**
     * @var int
     */
    private int $flushDepth = 0;

    /**
     * @var int
     */
    private const MAX_FLUSH_DEPTH = 10;

    /**
     * @var bool
     */
    private bool $hasPostEventChanges = false;

    /**
     * @var array<int, string>
     */
    private array $processedEvents = [];

    /**
     * @var bool
     */
    private bool $isInEventProcessing = false;

    /**
     * @param EntityManagerInterface $entityManager
     * @param EntityStateManager $stateManager
     * @param ChangeDetector $changeDetector
     * @param RelationManager $relationManager
     * @param InsertionProcessor $insertionProcessor
     * @param UpdateProcessor $updateProcessor
     * @param DeletionProcessor $deletionProcessor
     * @param EventManager|null $eventManager
     * @param ChangeSetManager $changeSetManager
     * @param IdentityMap $identityMap
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityStateManager $stateManager,
        private readonly ChangeDetector $changeDetector,
        private readonly RelationManager $relationManager,
        private readonly InsertionProcessor $insertionProcessor,
        private readonly UpdateProcessor $updateProcessor,
        private readonly DeletionProcessor $deletionProcessor,
        private readonly ?EventManager $eventManager,
        private readonly ChangeSetManager $changeSetManager,
        private readonly IdentityMap $identityMap
    ) {
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function persist(object $entity): void
    {
        // This is now handled by ChangeSetManager
        $this->changeSetManager->scheduleInsert($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $this->changeSetManager->scheduleDelete($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->changeSetManager->detach($entity);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function flush(): void
    {
        if ($this->isFlushInProgress) {
            return;
        }

        $this->isFlushInProgress = true;
        $this->flushDepth = 0;

        try {
            $this->doFlush();
        } finally {
            $this->isFlushInProgress = false;
            $this->hasPostEventChanges = false;
        }
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function doFlush(): void
    {
        $this->flushDepth++;

        if ($this->flushDepth > self::MAX_FLUSH_DEPTH) {
            throw new \RuntimeException('Maximum flush depth reached. Possible circular dependency.');
        }

        // Reset processed events for each flush cycle only at the top level
        if ($this->flushDepth === 1) {
            $this->processedEvents = [];
        }

        // Compute all change sets
        $this->changeSetManager->computeChangeSets();

        // Get entities to process
        $insertions = $this->changeSetManager->getScheduledInsertions();
        $updates = $this->changeSetManager->getScheduledUpdates();
        $deletions = $this->changeSetManager->getScheduledDeletions();

        // Process deletions first
        foreach ($deletions as $entity) {
            $this->processDeletion($entity);
        }

        // Process insertions (this gives entities their IDs)
        foreach ($insertions as $entity) {
            $this->processInsertion($entity);
        }

        // Process updates
        foreach ($updates as $entity) {
            $this->processUpdate($entity);
        }

        // IMPORTANT: Process relation changes AFTER entities have IDs
        $this->relationManager->processRelationChanges();

        // Execute any pending relation operations (like pivot table inserts)
        $this->relationManager->flush();

        // Check if there are new insertions from relation processing (like link entities)
        $newInsertions = $this->stateManager->getScheduledInsertions();
        if (!empty($newInsertions)) {
            foreach ($newInsertions as $entity) {
                $this->processInsertion($entity);
            }
        }

        // Clear change sets after successful flush but BEFORE post-flush events
        $this->changeSetManager->clearProcessedChanges();

        // Fire post-flush event if event manager is available - but LIMIT recursion
        if ($this->eventManager !== null && !$this->isInEventProcessing && $this->flushDepth <= 2) {
            $this->isInEventProcessing = true;

            // Store state before event
            $preEventInsertions = count($this->changeSetManager->getScheduledInsertions());
            $preEventUpdates = count($this->changeSetManager->getScheduledUpdates());
            $preEventDeletions = count($this->changeSetManager->getScheduledDeletions());

            $this->eventManager->dispatch(new PostFlushEvent($this->entityManager));

            // Check if new operations were scheduled during the event
            $postEventInsertions = count($this->changeSetManager->getScheduledInsertions());
            $postEventUpdates = count($this->changeSetManager->getScheduledUpdates());
            $postEventDeletions = count($this->changeSetManager->getScheduledDeletions());

            $hasNewChanges = $postEventInsertions > $preEventInsertions ||
                           $postEventUpdates > $preEventUpdates ||
                           $postEventDeletions > $preEventDeletions;

            $this->isInEventProcessing = false;

            // If there were changes during post-flush events, process them immediately
            if ($hasNewChanges) {
                // Process new insertions/updates created by post-flush events
                $newInsertions = array_slice($this->changeSetManager->getScheduledInsertions(), $preEventInsertions);
                $newUpdates = array_slice($this->changeSetManager->getScheduledUpdates(), $preEventUpdates);
                $newDeletions = array_slice($this->changeSetManager->getScheduledDeletions(), $preEventDeletions);

                foreach ($newDeletions as $entity) {
                    $this->processDeletion($entity);
                }

                foreach ($newInsertions as $entity) {
                    $this->processInsertion($entity);
                }

                foreach ($newUpdates as $entity) {
                    $this->processUpdate($entity);
                }

                // Clear the new changes immediately
                $this->changeSetManager->clearProcessedChanges();

                $this->hasPostEventChanges = true;
            }
        }

        // Check if there were changes during post events - but LIMIT recursion
        if ($this->hasPostEventChanges && $this->flushDepth < 3) {
            $this->hasPostEventChanges = false;
            $this->doFlush(); // Recursive flush for post-event changes
        }
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processInsertion(object $entity): void
    {
        // Call pre-persist event
        $this->callEntityEvent($entity, 'prePersist');

        // Process the insertion
        $this->insertionProcessor->process($entity);

        // Update entity state to MANAGED after successful insertion
        $this->stateManager->markAsPersisted($entity);

        // Update metadata to MANAGED state
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null) {
            $currentData = $this->changeDetector->extractCurrentData($entity);
            $newMetadata = new EntityMetadata(
                $metadata->className,
                $this->extractEntityId($entity) ?? $metadata->identifier,
                EntityState::MANAGED,
                $currentData,
                $metadata->loadedAt,
                new \DateTimeImmutable()
            );
            $this->identityMap->updateMetadata($entity, $newMetadata);
        }

        // Call post-persist event
        $this->callEntityEvent($entity, 'postPersist');
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processUpdate(object $entity): void
    {
        $changeSet = $this->changeSetManager->getChangeSet($entity);

        if ($changeSet === null || $changeSet->isEmpty()) {
            return;
        }

        // Call pre-update event
        $this->callEntityEvent($entity, 'preUpdate');

        // Convert ChangeSet to array format for update processor
        $changes = [];
        foreach ($changeSet->getChanges() as $property => $change) {
            $changes[$property] = [
                0 => $change->oldValue, // old value at index 0
                1 => $change->newValue  // new value at index 1
            ];
        }

        // Process the update
        $this->updateProcessor->process($entity, $changes);

        // Update metadata with new original data after successful update
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null) {
            $currentData = $this->changeDetector->extractCurrentData($entity);
            $newMetadata = new EntityMetadata(
                $metadata->className,
                $metadata->identifier,
                EntityState::MANAGED,
                $currentData, // Use current data as new original data
                $metadata->loadedAt,
                new \DateTimeImmutable()
            );
            $this->identityMap->updateMetadata($entity, $newMetadata);
        } else {
        }

        // Call post-update event
        $this->callEntityEvent($entity, 'postUpdate');
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processDeletion(object $entity): void
    {
        // Call pre-remove event
        $this->callEntityEvent($entity, 'preRemove');

        // Process the deletion
        $this->deletionProcessor->process($entity);

        // Update entity state
        $this->stateManager->markAsRemoved($entity);

        // Remove from identity map
        $this->identityMap->remove($entity);

        // Call post-remove event
        $this->callEntityEvent($entity, 'postRemove');
    }

    /**
     * @param object $entity
     * @return int|string|null
     */
    private function extractEntityId(object $entity): int|string|null
    {
        // Try common ID methods
        foreach (['getId', 'getIdentifier', 'getUuid'] as $method) {
            if (method_exists($entity, $method)) {
                $value = $entity->$method();
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param object $entity
     * @param string $eventName
     * @return void
     */
    private function callEntityEvent(object $entity, string $eventName): void
    {
        $entityId = spl_object_id($entity);
        $eventKey = $entityId . '_' . $eventName . '_' . $this->flushDepth; // Include flush depth to avoid cross-flush loops

        // Prevent infinite loops for the same event on the same entity at the same depth
        if (in_array($eventKey, $this->processedEvents, true)) {
            return;
        }

        // Add to processed events to prevent loops
        $this->processedEvents[] = $eventKey;

        // Call the event method if it exists on the entity
        if (method_exists($entity, $eventName)) {
            $entity->$eventName();
        }

        // Also dispatch global events if event manager is available
        if ($this->eventManager !== null) {
            switch ($eventName) {
                case 'prePersist':
                    $this->eventManager->dispatch(new \MulerTech\Database\Event\PrePersistEvent($entity, $this->entityManager));
                    break;
                case 'postPersist':
                    $this->eventManager->dispatch(new \MulerTech\Database\Event\PostPersistEvent($entity, $this->entityManager));
                    break;
                case 'preUpdate':
                    $changeSet = $this->changeSetManager->getChangeSet($entity);
                    $changes = [];
                    if ($changeSet !== null) {
                        foreach ($changeSet->getChanges() as $property => $change) {
                            $changes[$property] = [$change->oldValue, $change->newValue];
                        }
                    }
                    $this->eventManager->dispatch(new \MulerTech\Database\Event\PreUpdateEvent($entity, $this->entityManager, $changes));
                    break;
                case 'postUpdate':
                    // IMPORTANT: For postUpdate events, we need to immediately process any new entities
                    $this->eventManager->dispatch(new \MulerTech\Database\Event\PostUpdateEvent($entity, $this->entityManager));

                    // Check for new entities created during the event and process them immediately
                    $newInsertions = $this->changeSetManager->getScheduledInsertions();
                    if (!empty($newInsertions)) {
                        foreach ($newInsertions as $newEntity) {
                            $this->processInsertion($newEntity);
                        }
                        // Clear the processed insertions
                        $this->changeSetManager->clearProcessedChanges();
                    }
                    break;
                case 'preRemove':
                    $this->eventManager->dispatch(new \MulerTech\Database\Event\PreRemoveEvent($entity, $this->entityManager));
                    break;
                case 'postRemove':
                    $this->eventManager->dispatch(new \MulerTech\Database\Event\PostRemoveEvent($entity, $this->entityManager));
                    break;
            }
        }
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $originalData
     * @return void
     */
    public function manageNewEntity(object $entity, array $originalData): void
    {
        // Add to identity map
        $this->identityMap->add($entity);

        // Mark as managed
        $this->stateManager->manage($entity);

        // Store original data for change detection
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null) {
            $newMetadata = $metadata->withOriginalData($originalData);
            $this->identityMap->updateMetadata($entity, $newMetadata);
        }
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->changeSetManager->clear();
        $this->stateManager->clear();
        $this->processedEvents = [];
        $this->flushDepth = 0;
        $this->isInEventProcessing = false;
    }
}
