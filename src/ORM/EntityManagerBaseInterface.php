<?php

namespace MulerTech\Database\ORM;

interface EntityManagerBaseInterface
{
    /**
     * Récupère l'EntityManager
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface;

    /**
     * Trouve une entité par son ID
     *
     * @param string $entityName
     * @param mixed $id
     * @return object|null
     */
    public function find(string $entityName, $id): ?object;

    /**
     * Persiste une entité
     *
     * @param object $entity
     * @return void
     */
    public function persist(object $entity): void;

    /**
     * Exécute les opérations en attente
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Supprime une entité
     *
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void;
}