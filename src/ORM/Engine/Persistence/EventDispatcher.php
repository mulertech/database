<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PreRemoveEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\EventManager\EventManager;

/**
 * Handles event dispatching for persistence operations.
 *
 * @author Sébastien Muler
 */
class EventDispatcher
{
    /** @var array<string> */
    private array $processedEvents = [];

    private ?\Closure $postEventProcessor = null;

    public function __construct(
        private readonly ?EventManager $eventManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChangeSetManager $changeSetManager,
    ) {
    }

    public function setPostEventProcessor(\Closure $processor): void
    {
        $this->postEventProcessor = $processor;
    }

    public function callEntityEvent(object $entity, string $eventName, int $flushDepth): void
    {
        $entityId = spl_object_id($entity);
        $eventKey = $entityId.'_'.$eventName.'_'.$flushDepth;

        if (in_array($eventKey, $this->processedEvents, true)) {
            return;
        }

        $this->processedEvents[] = $eventKey;

        // Call the event method if it exists on the entity
        if (method_exists($entity, $eventName)) {
            $entity->$eventName();
        }

        // Dispatch global events if event manager is available
        if (null !== $this->eventManager) {
            $this->dispatchGlobalEvent($entity, $eventName);
        }
    }

    /**
     * Dispatch global event - only called when eventManager is not null.
     */
    private function dispatchGlobalEvent(object $entity, string $eventName): void
    {
        assert(
            null !== $this->eventManager,
            'EventManager must not be null when dispatching global events'
        );

        match ($eventName) {
            'prePersist' => $this->eventManager->dispatch(new PrePersistEvent($entity, $this->entityManager)),
            'postPersist' => $this->eventManager->dispatch(new PostPersistEvent($entity, $this->entityManager)),
            'preUpdate' => $this->dispatchPreUpdateEvent($entity),
            'postUpdate' => $this->dispatchPostUpdateEvent($entity),
            'preRemove' => $this->dispatchPreRemoveEvent($entity),
            'postRemove' => $this->dispatchPostRemoveEvent($entity),
            default => null,
        };
    }

    private function dispatchPreUpdateEvent(object $entity): void
    {
        assert(
            null !== $this->eventManager,
            'EventManager must not be null when dispatching events'
        );

        $changeSet = $this->changeSetManager->getChangeSet($entity);
        $this->eventManager->dispatch(new PreUpdateEvent($entity, $this->entityManager, $changeSet));
    }

    private function dispatchPostUpdateEvent(object $entity): void
    {
        assert(
            null !== $this->eventManager,
            'EventManager must not be null when dispatching events'
        );

        $this->eventManager->dispatch(new PostUpdateEvent($entity, $this->entityManager));
        $this->processPostEventOperations();
    }

    private function dispatchPreRemoveEvent(object $entity): void
    {
        assert(
            null !== $this->eventManager,
            'EventManager must not be null when dispatching events'
        );

        $this->eventManager->dispatch(new PreRemoveEvent($entity, $this->entityManager));
        $this->processPostEventOperations();
    }

    private function dispatchPostRemoveEvent(object $entity): void
    {
        assert(
            null !== $this->eventManager,
            'EventManager must not be null when dispatching events'
        );

        $this->eventManager->dispatch(new PostRemoveEvent($entity, $this->entityManager));
        $this->processPostEventOperations();
    }

    private function processPostEventOperations(): void
    {
        // First compute any new change sets
        $this->changeSetManager->computeChangeSets();

        // If we have a post-event processor, use it to handle the operations
        if (null !== $this->postEventProcessor) {
            ($this->postEventProcessor)();
        }

        // Clear processed changes
        $this->changeSetManager->clearProcessedChanges();
    }

    public function resetProcessedEvents(): void
    {
        $this->processedEvents = [];
    }
}
