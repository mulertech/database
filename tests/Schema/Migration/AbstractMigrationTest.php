<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Schema\Migration\Entity\MigrationHistory;
use MulerTech\Database\Tests\Files\Schema\Migration\InvalidMigrationForTesting;
use MulerTech\Database\Tests\Files\Schema\Migration\Migration202501010000;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;

class AbstractMigrationTest extends TestCase
{
    private EntityManager $entityManager;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        $entitiesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';
        $metadataRegistry = new MetadataRegistry($entitiesPath);
        $metadataRegistry->getEntityMetadata(MigrationHistory::class);
        
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $metadataRegistry,
        );
    }

    public function testInvalidMigrationClassNameFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid migration class name format. Expected: MigrationYYYYMMDDHHMM');

        new InvalidMigrationForTesting($this->entityManager);
    }

    public function testValidMigrationClassNameFormat(): void
    {
        $migration = new Migration202501010000($this->entityManager);
        
        $this->assertEquals('20250101-0000', $migration->getVersion());
    }

    public function testCreateQueryBuilder(): void
    {
        $migration = new Migration202501010000($this->entityManager);
        
        $queryBuilder = $migration->createQueryBuilder();
        
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }
}