<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * Validates entities before UPDATE operations.
 *
 * @author Sébastien Muler
 */
readonly class UpdateEntityValidator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MetadataRegistry $metadataRegistry,
    ) {
    }

    /**
     * Validate entity is ready for update.
     */
    public function validateForUpdate(object $entity): bool
    {
        $entityId = $this->getEntityId($entity);
        if (null === $entityId) {
            return false;
        }

        return $this->entityExistsInDatabase($entity);
    }

    /**
     * Get entity ID with validation.
     */
    public function getEntityId(object $entity): int|string|null
    {
        if (!method_exists($entity, 'getId')) {
            throw new \RuntimeException(sprintf('The entity %s must have a getId method', $entity::class));
        }

        return $entity->getId();
    }

    private function entityExistsInDatabase(object $entity): bool
    {
        try {
            $entityId = $this->getEntityId($entity);
            if (null === $entityId) {
                return false;
            }

            $tableName = $this->metadataRegistry->getEntityMetadata($entity::class)->tableName;

            $pdo = $this->entityManager->getPdm();
            $statement = $pdo->prepare("SELECT COUNT(*) FROM `$tableName` WHERE id = :id");
            $statement->execute(['id' => $entityId]);
            $result = $statement->fetchColumn();
            $count = is_numeric($result) ? (int) $result : 0;
            $statement->closeCursor();

            return $count > 0;
        } catch (\Exception) {
            // If we can't check, assume it exists to avoid silent failures
            return true;
        }
    }
}
