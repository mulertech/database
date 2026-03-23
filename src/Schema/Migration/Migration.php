<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\QueryBuilder;

/**
 * Class Migration.
 *
 * Abstract Migration class that all migrations must extend
 *
 * @author Sébastien Muler
 */
abstract class Migration
{
    /**
     * @var string Migration version in format YYYYMMDD-HHMM
     */
    protected string $version;

    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
        $this->initializeMetadata();
    }

    /**
     * Initialize migration metadata from class name.
     */
    private function initializeMetadata(): void
    {
        $className = basename(str_replace('\\', '/', static::class));

        // Extract version from class name. Example: Migration202504111016
        if (!preg_match('/^Migration(\d{8})(\d{4})$/', $className, $matches)) {
            throw new \RuntimeException('Invalid migration class name format. Expected: MigrationYYYYMMDDHHMM');
        }

        $this->version = $matches[1].'-'.$matches[2];
    }

    /**
     * Get migration version number.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Create a new QueryBuilder instance.
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->entityManager->getEmEngine());
    }

    /**
     * Execute migration up (make changes to database).
     */
    abstract public function up(): void;

    /**
     * Execute migration down (revert changes from database).
     */
    abstract public function down(): void;
}
