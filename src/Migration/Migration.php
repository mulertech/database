<?php

namespace MulerTech\Database\Migration;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use RuntimeException;

/**
 * Abstract Migration class that all migrations must extend
 * 
 * @package MulerTech\Database\Migration
 * @author Sébastien Muler
 */
abstract class Migration
{
    /**
     * @var string Migration version in format YYYYMMDD-HHMM
     */
    protected string $version;
    
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
        $this->initializeMetadata();
    }
    
    /**
     * Initialize migration metadata from class name
     * 
     * @return void
     */
    private function initializeMetadata(): void
    {
        $className = basename(str_replace('\\', '/', static::class));

        // Extract version from class name. Example: Migration202504111016
        if (!preg_match('/^Migration(\d{8})(\d{4})$/', $className, $matches)) {
            throw new RuntimeException('Invalid migration class name format. Expected: MigrationYYYYMMDDHHMM');
        }

        $this->version = $matches[1] . '-' . $matches[2];
    }
    
    /**
     * Get migration version number
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }
    
    /**
     * Create a new QueryBuilder instance
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->entityManager->getEmEngine());
    }
    
    /**
     * Execute migration up (make changes to database)
     *
     * @return void
     */
    abstract public function up(): void;
    
    /**
     * Execute migration down (revert changes from database)
     *
     * @return void
     */
    abstract public function down(): void;
}
