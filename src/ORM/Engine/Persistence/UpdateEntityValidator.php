<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use Exception;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * Validates entities before UPDATE operations
 */
readonly class UpdateEntityValidator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MetadataCache $metadataCache
    ) {
    }

    /**
     * Validate entity is ready for update
     *
     * @param object $entity
     * @return bool
     */
    public function validateForUpdate(object $entity): bool
    {
        $entityId = $this->getEntityId($entity);
        if ($entityId === null) {
            return false;
        }

        return $this->entityExistsInDatabase($entity);
    }

    /**
     * Get entity ID with validation
     *
     * @param object $entity
     * @return int|string|null
     */
    public function getEntityId(object $entity): int|string|null
    {
        if (!method_exists($entity, 'getId')) {
            throw new RuntimeException(
                sprintf('The entity %s must have a getId method', $entity::class)
            );
        }

        return $entity->getId();
    }

    /**
     * Check if entity exists in database
     *
     * @param object $entity
     * @return bool
     */
    private function entityExistsInDatabase(object $entity): bool
    {
        try {
            $entityId = $this->getEntityId($entity);
            if ($entityId === null) {
                return false;
            }

            $tableName = $this->metadataCache->getTableName($entity::class);

            $pdo = $this->entityManager->getPdm();
            $statement = $pdo->prepare("SELECT COUNT(*) FROM `$tableName` WHERE id = :id");
            $statement->execute(['id' => $entityId]);
            $result = $statement->fetchColumn();
            $count = is_numeric($result) ? (int) $result : 0;
            $statement->closeCursor();

            return $count > 0;
        } catch (Exception) {
            // If we can't check, assume it exists to avoid silent failures
            return true;
        }
    }
}
