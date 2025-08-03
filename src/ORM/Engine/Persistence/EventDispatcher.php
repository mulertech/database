<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use Closure;
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
 * Handles event dispatching for persistence operations
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EventDispatcher
{
    /** @var array<string> */
    private array $processedEvents = [];

    /** @var Closure|null */
    private ?Closure $postEventProcessor = null;

    public function __construct(
        private readonly ?EventManager $eventManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChangeSetManager $changeSetManager
    ) {
    }

    /**
     * @param Closure $processor
     * @return void
     */
    public function setPostEventProcessor(Closure $processor): void
    {
        $this->postEventProcessor = $processor;
    }

    /**
     * @param object $entity
     * @param string $eventName
     * @param int $flushDepth
     * @return void
     */
    public function callEntityEvent(object $entity, string $eventName, int $flushDepth): void
    {
        $entityId = spl_object_id($entity);
        $eventKey = $entityId . '_' . $eventName . '_' . $flushDepth;

        if (in_array($eventKey, $this->processedEvents, true)) {
            return;
        }

        $this->processedEvents[] = $eventKey;

        // Call the event method if it exists on the entity
        if (method_exists($entity, $eventName)) {
            $entity->$eventName();
        }

        // Dispatch global events if event manager is available
        if ($this->eventManager !== null) {
            $this->dispatchGlobalEvent($entity, $eventName);
        }
    }

    /**
     * @param object $entity
     * @param string $eventName
     * @return void
     */
    private function dispatchGlobalEvent(object $entity, string $eventName): void
    {
        if ($this->eventManager === null) {
            return;
        }

        match ($eventName) {
            'prePersist' => $this->eventManager->dispatch(new PrePersistEvent($entity, $this->entityManager)),
            'postPersist' => $this->eventManager->dispatch(new PostPersistEvent($entity, $this->entityManager)),
            'preUpdate' => $this->dispatchPreUpdateEvent($entity),
            'postUpdate' => $this->dispatchPostUpdateEvent($entity),
            'preRemove' => $this->dispatchPreRemoveEvent($entity),
            'postRemove' => $this->dispatchPostRemoveEvent($entity),
            default => null
        };
    }

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPreUpdateEvent(object $entity): void
    {
        if ($this->eventManager === null) {
            return;
        }
        $changeSet = $this->changeSetManager->getChangeSet($entity);
        $this->eventManager->dispatch(new PreUpdateEvent($entity, $this->entityManager, $changeSet));
    }

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPostUpdateEvent(object $entity): void
    {
        if ($this->eventManager === null) {
            return;
        }
        $this->eventManager->dispatch(new PostUpdateEvent($entity, $this->entityManager));
        $this->processPostEventOperations();
    }

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPreRemoveEvent(object $entity): void
    {
        if ($this->eventManager === null) {
            return;
        }
        $this->eventManager->dispatch(new PreRemoveEvent($entity, $this->entityManager));
        $this->processPostEventOperations();
    }

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPostRemoveEvent(object $entity): void
    {
        if ($this->eventManager === null) {
            return;
        }
        $this->eventManager->dispatch(new PostRemoveEvent($entity, $this->entityManager));
        $this->processPostEventOperations();
    }

    /**
     * @return void
     */
    private function processPostEventOperations(): void
    {
        // First compute any new change sets
        $this->changeSetManager->computeChangeSets();

        // If we have a post-event processor, use it to handle the operations
        if ($this->postEventProcessor !== null) {
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
