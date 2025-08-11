<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Schema\Migration\Entity\MigrationHistory;
use MulerTech\Database\Schema\Migration\Migration;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;

class AbstractMigrationTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $entitiesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';
        $metadataCache = new MetadataCache(null, $entitiesPath);
        $metadataCache->getEntityMetadata(MigrationHistory::class);
        
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $metadataCache,
        );
    }

    public function testInvalidMigrationClassNameFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid migration class name format. Expected: MigrationYYYYMMDDHHMM');

        new InvalidMigrationForTest($this->entityManager);
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
        
        $this->assertInstanceOf('MulerTech\Database\Query\Builder\QueryBuilder', $queryBuilder);
    }
}

// Test classes defined in same file to avoid autoloading issues
class InvalidMigrationForTest extends Migration
{
    public function up(): void {}
    public function down(): void {}
}

class Migration202501010000 extends Migration
{
    public function up(): void {}
    public function down(): void {}
}