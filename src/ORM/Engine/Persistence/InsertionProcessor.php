<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use Exception;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\InsertBuilder;
use MulerTech\Database\Query\Builder\QueryBuilder;
use ReflectionException;
use RuntimeException;

/**
 * Class InsertionProcessor
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class InsertionProcessor
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param MetadataRegistry $metadataRegistry
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MetadataRegistry $metadataRegistry
    ) {
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function process(object $entity): void
    {
        // Check if entity already has an ID
        $entityId = $this->getId($entity);
        if ($entityId !== null) {
            // Entity already has an ID, skip insertion
            return;
        }

        // Extract all properties as changes for insertion
        $changes = $this->extractEntityData($entity);
        $this->execute($entity, $changes);
    }

    /**
     * @param object $entity
     * @param array<string, array<int, mixed>> $changes
     * @return void
     * @throws ReflectionException
     */
    public function execute(object $entity, array $changes): void
    {
        // Double-check that entity doesn't have an ID before executing
        $entityId = $this->getId($entity);
        if ($entityId !== null) {
            return;
        }

        $insertBuilder = $this->buildInsertQuery($entity, $changes);

        $pdoStatement = $insertBuilder->getResult();
        $pdoStatement->execute();

        $this->setGeneratedId($entity);

        $pdoStatement->closeCursor();
    }

    /**
     * @param object $entity
     * @param array<string, array<int, mixed>> $changes
     * @return InsertBuilder
     * @throws ReflectionException
     * @throws Exception
     */
    private function buildInsertQuery(object $entity, array $changes): InsertBuilder
    {
        $insertBuilder = new QueryBuilder($this->entityManager->getEmEngine())
            ->insert($this->getTableName($entity::class));

        $propertiesColumns = $this->getPropertiesColumns($entity::class);

        foreach ($propertiesColumns as $property => $column) {
            if (!isset($changes[$property][1])) {
                continue;
            }

            $value = $changes[$property][1];

            // If it's an object (relation), retrieve its ID
            if (is_object($value)) {
                $value = $this->getId($value);
            }

            $insertBuilder->set($column, $value);
        }

        return $insertBuilder;
    }

    /**
     * @param object $entity
     * @return void
     */
    private function setGeneratedId(object $entity): void
    {
        $lastId = $this->entityManager->getPdm()->lastInsertId();

        if (!empty($lastId)) {
            if (!method_exists($entity, 'setId')) {
                throw new RuntimeException(
                    sprintf('The entity %s must have a setId method', $entity::class)
                );
            }

            $entity->setId((int)$lastId);
        }
    }

    /**
     * @param object $entity
     * @param string $property
     * @return mixed
     * @throws ReflectionException
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        $metadata = $this->metadataRegistry->getEntityMetadata($entity::class);
        $getter = $metadata->getRequiredGetter($property);
        return $entity->$getter();
    }

    /**
     * @param class-string $entityName
     * @return string
     * @throws Exception
     */
    private function getTableName(string $entityName): string
    {
        return $this->metadataRegistry->getTableName($entityName);
    }

    /**
     * @param class-string $entityName
     * @return array<string, string>
     * @throws ReflectionException|Exception
     */
    private function getPropertiesColumns(string $entityName): array
    {
        return $this->metadataRegistry->getPropertiesColumns($entityName);
    }

    /**
     * @param object $entity
     * @return int|null
     */
    private function getId(object $entity): ?int
    {
        if (!method_exists($entity, 'getId')) {
            throw new RuntimeException(
                sprintf('The entity %s must have a getId method', $entity::class)
            );
        }

        return $entity->getId();
    }

    /**
     * Extract all entity data as changes for insertion
     *
     * @param object $entity
     * @return array<string, array<int, mixed>>
     * @throws ReflectionException
     */
    private function extractEntityData(object $entity): array
    {
        $changes = [];
        $propertiesColumns = $this->getPropertiesColumns($entity::class);

        foreach ($propertiesColumns as $property => $column) {
            $value = $this->getPropertyValue($entity, $property);
            // Format as [old_value, new_value] where old is null for new entities
            $changes[$property] = [null, $value];
        }

        return $changes;
    }
}
