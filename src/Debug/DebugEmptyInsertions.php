<?php

namespace MulerTech\Database\Debug;

use MulerTech\Database\ORM\EntityManager;

/**
 * Debug helper to understand why getScheduledInsertions returns empty array
 * @package MulerTech\Database\Debug
 * @author Sébastien Muler
 */
class DebugEmptyInsertions
{
    private EntityManager $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Debug the current state of scheduled insertions
     */
    public function debug(): void
    {
        echo "=== DEBUG EMPTY SCHEDULED INSERTIONS ===\n\n";

        // Create a test entity
        $entity = new TestEntity();
        $entity->setName("Test Entity");

        echo "1. Before persist:\n";
        $this->printScheduledCounts();

        // Persist the entity
        $this->em->persist($entity);

        echo "\n2. After persist:\n";
        $this->printScheduledCounts();

        // Get the engine components
        $engine = $this->em->getEmEngine();
        $stateManager = $engine->getStateManager();
        $changeSetManager = $engine->getChangeSetManager();

        echo "\n3. Detailed analysis:\n";

        // Check StateManager
        echo "StateManager type: " . get_class($stateManager) . "\n";
        $stateInsertions = $stateManager->getScheduledInsertions();
        echo "StateManager scheduled insertions: " . count($stateInsertions) . "\n";

        // Check ChangeSetManager
        $changeInsertions = $changeSetManager->getScheduledInsertions();
        echo "ChangeSetManager scheduled insertions: " . count($changeInsertions) . "\n";

        // Check if entity is managed
        $isManaged = $engine->isManaged($entity);
        echo "Entity is managed: " . ($isManaged ? 'YES' : 'NO') . "\n";

        // Check entity state
        $identityMap = $engine->getIdentityMap();
        $entityState = $identityMap->getEntityState($entity);
        echo "Entity state: " . ($entityState ? $entityState->value : 'NULL') . "\n";

        // Check if StateManager has ChangeSetManager reference
        if (method_exists($stateManager, 'hasChangeSetManager')) {
            echo "StateManager has ChangeSetManager: " .
                ($stateManager->hasChangeSetManager() ? 'YES' : 'NO') . "\n";
        }

        echo "\n4. RelationManager check:\n";
        $relationManager = $engine->getRelationManager();

        // Simulate what RelationManager does
        $scheduled = $stateManager->getScheduledInsertions();
        echo "What RelationManager sees: " . count($scheduled) . " insertions\n";

        if (count($scheduled) === 0 && count($changeInsertions) > 0) {
            echo "\n❌ PROBLEM IDENTIFIED:\n";
            echo "ChangeSetManager has insertions but StateManager doesn't see them!\n";
            echo "This means the two systems are not connected.\n";
            echo "\nSOLUTION: Apply the fixes from the migration guide.\n";
        } elseif (count($scheduled) > 0) {
            echo "\n✅ WORKING CORRECTLY:\n";
            echo "StateManager can see the scheduled insertions.\n";
        } else {
            echo "\n⚠️ NO INSERTIONS:\n";
            echo "Neither system has scheduled insertions.\n";
            echo "Check if persist() is working correctly.\n";
        }
    }

    /**
     * Print current scheduled counts
     */
    private function printScheduledCounts(): void
    {
        $engine = $this->em->getEmEngine();

        echo "- Scheduled insertions: " . count($engine->getScheduledInsertions()) . "\n";
        echo "- Scheduled updates: " . count($engine->getScheduledUpdates()) . "\n";
        echo "- Scheduled deletions: " . count($engine->getScheduledDeletions()) . "\n";
    }
}

/**
 * Simple test entity
 */
class TestEntity
{
    private ?int $id = null;
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

// Usage:
// $debug = new DebugEmptyInsertions($entityManager);
// $debug->debug();
