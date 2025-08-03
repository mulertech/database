<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use Exception;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\PropertyChange;
use ReflectionException;
use RuntimeException;

/**
 * Simplified UpdateProcessor using specialized components
 *
 * Orchestrates entity updates by delegating to specialized processors
 */
readonly class UpdateProcessor
{
    private UpdateEntityValidator $validator;
    private UpdateQueryBuilder $queryBuilder;

    public function __construct(
        EntityManagerInterface $entityManager,
        MetadataCache $metadataCache
    ) {
        $this->validator = new UpdateEntityValidator($entityManager, $metadataCache);
        $this->queryBuilder = new UpdateQueryBuilder(
            $entityManager,
            $metadataCache,
            $this->validator
        );
    }

    /**
     * Process entity update with given changes
     *
     * @param object $entity
     * @param array<string, PropertyChange> $changes
     * @return void
     * @throws ReflectionException
     */
    public function process(object $entity, array $changes): void
    {
        if (empty($changes)
            || !$this->validator->validateForUpdate($entity)
            || !$this->queryBuilder->hasValidUpdates($entity, $changes)
        ) {
            return;
        }

        $this->executeUpdate($entity, $changes);
    }

    /**
     * Execute the UPDATE operation
     *
     * @param object $entity
     * @param array<string, PropertyChange> $changes
     * @return void
     */
    private function executeUpdate(object $entity, array $changes): void
    {
        try {
            $updateBuilder = $this->queryBuilder->buildQuery($entity, $changes);
            $pdoStatement = $updateBuilder->getResult();

            $pdoStatement->execute();
            $pdoStatement->closeCursor();

        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to update entity %s: %s', $entity::class, $e->getMessage()),
                0,
                $e
            );
        }
    }
}
