<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use Exception;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\PropertyChange;
use ReflectionException;
use RuntimeException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class UpdateProcessor
{
    private UpdateEntityValidator $validator;
    private UpdateQueryBuilder $queryBuilder;

    public function __construct(
        EntityManagerInterface $entityManager,
        MetadataRegistry $metadataRegistry
    ) {
        $this->validator = new UpdateEntityValidator($entityManager, $metadataRegistry);
        $this->queryBuilder = new UpdateQueryBuilder(
            $entityManager,
            $metadataRegistry,
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
