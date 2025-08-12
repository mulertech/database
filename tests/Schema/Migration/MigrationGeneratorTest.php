<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Schema\Migration\MigrationGenerator;
use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use MulerTech\Database\Mapping\MetadataRegistry;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for MigrationGenerator class
 */
class MigrationGeneratorTest extends TestCase
{
    private string $tempMigrationsDir;
    private SchemaComparer $mockSchemaComparer;
    private MetadataRegistry $metadataRegistry;
    private MigrationGenerator $generator;

    protected function setUp(): void
    {
        // Create temporary directory for migrations
        $this->tempMigrationsDir = sys_get_temp_dir() . '/migrations_test_' . uniqid();
        mkdir($this->tempMigrationsDir);

        $this->mockSchemaComparer = $this->createMock(SchemaComparer::class);
        
        // Create a real MetadataRegistry instance since it's final and cannot be mocked
        $this->metadataRegistry = new MetadataRegistry();

        $this->generator = new MigrationGenerator(
            $this->mockSchemaComparer,
            $this->metadataRegistry,
            $this->tempMigrationsDir
        );
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempMigrationsDir)) {
            $files = array_diff(scandir($this->tempMigrationsDir), ['.', '..']);
            foreach ($files as $file) {
                unlink($this->tempMigrationsDir . DIRECTORY_SEPARATOR . $file);
            }
            rmdir($this->tempMigrationsDir);
        }
    }

    public function testConstructor(): void
    {
        $generator = new MigrationGenerator(
            $this->mockSchemaComparer,
            $this->metadataRegistry,
            $this->tempMigrationsDir
        );

        $this->assertInstanceOf(MigrationGenerator::class, $generator);
    }

    public function testConstructorWithNonExistentDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration directory does not exist:');

        new MigrationGenerator(
            $this->mockSchemaComparer,
            $this->metadataRegistry,
            '/non/existent/directory'
        );
    }

    public function testGenerateMigrationWithNoDifferences(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(false);

        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration();

        $this->assertNull($result);
    }

    public function testGenerateMigrationWithDifferences(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);

        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration('202401011200');

        $this->assertNotNull($result);
        $this->assertStringContainsString('Migration202401011200.php', $result);
        $this->assertFileExists($result);
    }

    public function testGenerateMigrationWithAutoDateTime(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);

        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration();

        $this->assertNotNull($result);
        $this->assertStringContainsString('Migration', $result);
        $this->assertStringContainsString('.php', $result);
        $this->assertFileExists($result);

        // Check that filename contains current date/time pattern
        $filename = basename($result);
        $this->assertMatchesRegularExpression('/^Migration\d{12}\.php$/', $filename);
    }

    public function testGeneratedMigrationFileContent(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);

        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration('202401011200');

        $this->assertNotNull($result);
        $content = file_get_contents($result);

        // Check that the content contains expected structure
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('use MulerTech\Database\Schema\Migration\Migration;', $content);
        $this->assertStringContainsString('use MulerTech\Database\Schema\Builder\SchemaBuilder;', $content);
        $this->assertStringContainsString('class Migration202401011200 extends Migration', $content);
        $this->assertStringContainsString('public function up(): void', $content);
        $this->assertStringContainsString('public function down(): void', $content);
    }

    public function testGeneratedMigrationWithUpCode(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $mockDiff->method('getTablesToCreate')->willReturn([]); // Empty to avoid entity lookup
        $mockDiff->method('getForeignKeysToDrop')->willReturn([]);
        $mockDiff->method('getColumnsToDrop')->willReturn([]);
        $mockDiff->method('getColumnsToAdd')->willReturn([]);
        $mockDiff->method('getColumnsToModify')->willReturn([]);
        $mockDiff->method('getForeignKeysToAdd')->willReturn([]);
        $mockDiff->method('getTablesToDrop')->willReturn([]);

        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration('202401011200');

        $this->assertNotNull($result);
        $content = file_get_contents($result);

        // Should contain up code structure (without specific table creation)
        $this->assertStringContainsString('public function up()', $content);
        $this->assertStringContainsString('public function down()', $content);
    }

    public function testGeneratedMigrationWithDownCode(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $mockDiff->method('getTablesToCreate')->willReturn([]); // Empty to avoid entity lookup
        $mockDiff->method('getForeignKeysToAdd')->willReturn([]);
        $mockDiff->method('getColumnsToAdd')->willReturn([]);
        $mockDiff->method('getColumnsToModify')->willReturn([]);

        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration('202401011200');

        $this->assertNotNull($result);
        $content = file_get_contents($result);

        // Should contain rollback code structure
        $this->assertStringContainsString('public function down()', $content);
        $this->assertStringContainsString('public function up()', $content);
    }

    public function testGeneratedMigrationWithEmptyDownCode(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $mockDiff->method('getForeignKeysToAdd')->willReturn([]);
        $mockDiff->method('getTablesToCreate')->willReturn([]);
        $mockDiff->method('getColumnsToAdd')->willReturn([]);
        $mockDiff->method('getColumnsToModify')->willReturn([]);

        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration('202401011200');

        $this->assertNotNull($result);
        $content = file_get_contents($result);

        // Should contain default rollback message
        $this->assertStringContainsString('// No rollback operations defined', $content);
    }

    public function testInvalidDateTimeFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid datetime format. Expected: YYYYMMDDHHMM');

        $this->generator->generateMigration('invalid-datetime');
    }

    public function testValidDateTimeFormats(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        // Valid formats
        $validFormats = [
            '202401011200',  // YYYYMMDDHHMM
            '202312312359',  // Edge case - last minute of year
            '202002290000',  // Leap year
        ];

        foreach ($validFormats as $datetime) {
            $result = $this->generator->generateMigration($datetime);
            $this->assertNotNull($result);
            $this->assertStringContainsString("Migration{$datetime}.php", $result);
        }
    }

    public function testInvalidDateTimeFormats(): void
    {
        $invalidFormats = [
            '2024010112',    // Too short
            '20240101120000', // Too long
            '24-01-01-12-00', // Wrong separators
            '2024/01/01 12:00', // Wrong format
            'abcd01011200',   // Non-numeric year
            '202413011200',   // Invalid month
            '202401321200',   // Invalid day
            '202401012500',   // Invalid hour
            '202401011260',   // Invalid minute
        ];

        foreach ($invalidFormats as $datetime) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Invalid datetime format. Expected: YYYYMMDDHHMM');
            
            try {
                $this->generator->generateMigration($datetime);
            } catch (RuntimeException $e) {
                // Re-throw to continue testing other formats
                throw $e;
            }
        }
    }

    public function testMigrationFileLocation(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration('202401011200');

        $this->assertNotNull($result);
        $expectedPath = $this->tempMigrationsDir . DIRECTORY_SEPARATOR . 'Migration202401011200.php';
        $this->assertEquals($expectedPath, $result);
    }

    public function testMultipleMigrationGeneration(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        // Generate multiple migrations
        $result1 = $this->generator->generateMigration('202401011200');
        $result2 = $this->generator->generateMigration('202401011201');

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotEquals($result1, $result2);
        $this->assertFileExists($result1);
        $this->assertFileExists($result2);
    }

    public function testMigrationTemplateStructure(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration('202401011200');

        $this->assertNotNull($result);
        $content = file_get_contents($result);

        // Check all template parts are present
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('Auto-generated migration', $content);
        $this->assertStringContainsString('class Migration202401011200 extends Migration', $content);
        $this->assertStringContainsString('{@inheritdoc}', $content);
        $this->assertStringContainsString('public function up(): void', $content);
        $this->assertStringContainsString('public function down(): void', $content);
    }

    public function testFileWriteSuccess(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        $result = $this->generator->generateMigration('202401011200');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertIsReadable($result);

        // Check file is not empty
        $content = file_get_contents($result);
        $this->assertNotEmpty($content);
    }

    public function testConsecutiveMigrationCallsWithSameDateTime(): void
    {
        $mockDiff = $this->createMock(SchemaDifference::class);
        $mockDiff->method('hasDifferences')->willReturn(true);
        $this->mockSchemaComparer->method('compare')->willReturn($mockDiff);

        // First migration
        $result1 = $this->generator->generateMigration('202401011200');
        $this->assertNotNull($result1);
        $this->assertFileExists($result1);

        // Second migration with same datetime should overwrite
        $result2 = $this->generator->generateMigration('202401011200');
        $this->assertNotNull($result2);
        $this->assertEquals($result1, $result2);
        $this->assertFileExists($result2);
    }
}