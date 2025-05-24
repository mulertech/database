<?php

namespace MulerTech\Database\ORM;

abstract class EntityManagerBase implements EntityManagerBaseInterface
{
    protected EntityManagerInterface $entityManager;

    /**
     * Constructeur
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Récupère l'EntityManager
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Trouve une entité par son ID
     *
     * @param string $entityName
     * @param mixed $id
     * @return object|null
     */
    public function find(string $entityName, $id): ?object
    {
        return $this->entityManager->find($entityName, $id);
    }

    /**
     * Persiste une entité
     *
     * @param object $entity
     * @return void
     */
    public function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    /**
     * Exécute les opérations en attente
     *
     * @return void
     */
    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Supprime une entité
     *
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
    }

    /**
     * Compare deux objets pour déterminer les modifications
     *
     * @param object $old_item
     * @param object $new_item
     * @return array|null
     */
    abstract protected function compareUpdateItem(object $old_item, object $new_item): ?array;
}
