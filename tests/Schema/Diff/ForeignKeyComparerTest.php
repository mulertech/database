<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Diff;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Schema\Diff\ForeignKeyComparer;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use MulerTech\Database\Tests\Files\Mapping\EntityWithNullReferencedTable;
use MulerTech\Database\Tests\Files\Mapping\EntityWithNullReferencedColumn;

/**
 * Test cases for ForeignKeyComparer class
 */
class ForeignKeyComparerTest extends TestCase
{
    private ForeignKeyComparer $comparer;
    private MetadataCache $metadataCache;

    protected function setUp(): void
    {
        // Create a real MetadataCache instance for testing
        $this->metadataCache = new MetadataCache();
        $this->comparer = new ForeignKeyComparer($this->metadataCache);
        $this->diff = new SchemaDifference();
    }

    public function testGetForeignKeyInfoThrowsExceptionWhenNotFullyDefined(): void
    {
        $entityClass = EntityWithNullReferencedTable::class;
        $property = 'someId';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Foreign key for MulerTech\Database\Tests\Files\Mapping\EntityWithNullReferencedTable::someId is not fully defined in entity metadata");

        // Use reflection to access the private method
        $reflection = new ReflectionClass($this->comparer);
        $method = $reflection->getMethod('getForeignKeyInfo');

        $method->invoke($this->comparer, $entityClass, $property);
    }

    public function testGetForeignKeyInfoThrowsExceptionWhenReferencedColumnIsNull(): void
    {
        $entityClass = EntityWithNullReferencedColumn::class;
        $property = 'someId';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Foreign key for MulerTech\Database\Tests\Files\Mapping\EntityWithNullReferencedColumn::someId is not fully defined in entity metadata");

        // Use reflection to access the private method
        $reflection = new ReflectionClass($this->comparer);
        $method = $reflection->getMethod('getForeignKeyInfo');

        $method->invoke($this->comparer, $entityClass, $property);
    }
}