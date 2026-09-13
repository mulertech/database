<?php

namespace MulerTech\Database\Tests\Schema\Migration\Command;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\DriverFactory;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use MulerTech\Database\Schema\Migration\Command\MigrationGenerateCommand;
use MulerTech\Database\Schema\Migration\MigrationGenerator;
use MulerTech\MTerm\Core\Terminal;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MigrationGenerateCommandTest extends TestCase
{
    private Terminal $terminal;
    private EntityManager $entityManager;
    private string $migrationsDirectory;
    private MigrationGenerateCommand $command;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->terminal = $this->createStub(Terminal::class);
        // Create MetadataRegistry with automatic entity loading from test directory
        $entitiesPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';
        $metadataRegistry = new MetadataRegistry($entitiesPath);
        $scheme = getenv('DATABASE_SCHEME') ?: 'mysql';
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(DriverFactory::create($scheme)), []),
            $metadataRegistry
        );
        $this->migrationsDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations';
        
        $this->command = new MigrationGenerateCommand(
            $this->terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );
        
        if (!is_dir($this->migrationsDirectory)) {
            mkdir($this->migrationsDirectory, 0777, true);
        }
    }
    
    protected function tearDown(): void
    {
        if (is_dir($this->migrationsDirectory)) {
            $files = glob($this->migrationsDirectory . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->migrationsDirectory);
        }
    }

    public function testExecuteSuccessfulMigrationGeneration(): void
    {
        // Fresh terminal stub, isolated to this test
        $terminal = $this->createStub(Terminal::class);

        $migrationGenerateCommand = new MigrationGenerateCommand(
            $terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        // No exact call count, so the test does not depend on how many lines are written
        $terminal->method('writeLine');

        $this->assertEquals(0, $migrationGenerateCommand->execute(['202302151000']));
        $this->assertTrue(
            file_exists($this->migrationsDirectory . DIRECTORY_SEPARATOR . 'Migration202302151000.php'),
            'Migration file should be created'
        );
    }

    /**
     * @throws Exception
     */
    public function testExecuteNoChangesDetected(): void
    {
        // Fresh terminal stub, isolated to this test
        $terminal = $this->createStub(Terminal::class);

        // SchemaComparer stub reporting no differences
        $schemaComparer = $this->createStub(SchemaComparer::class);
        $schemaComparer->method('compare')->willReturn(
            new SchemaDifference([], [], [], [], [], [], [], [])
        );

        // MigrationGenerator mock built on the stubbed SchemaComparer
        $migrationGenerator = $this->getMockBuilder(MigrationGenerator::class)
            ->setConstructorArgs([$schemaComparer, $this->entityManager->getMetadataRegistry(), $this->migrationsDirectory])
            ->disableOriginalConstructor()
            ->getMock();
        $migrationGenerator->expects($this->once())->method('generateMigration')->willReturn(null);

        // Anonymous subclass injecting the mock into the command
        $command = new class($terminal, $this->entityManager, $this->migrationsDirectory, $migrationGenerator) extends MigrationGenerateCommand {
            private MigrationGenerator $mockedMigrationGenerator;

            public function __construct(Terminal $terminal, $entityManager, $migrationsDirectory, $migrationGenerator)
            {
                parent::__construct($terminal, $entityManager, $migrationsDirectory);
                $this->mockedMigrationGenerator = $migrationGenerator;
            }

            protected function createMigrationGenerator($schemaComparer, $migrationsDirectory): MigrationGenerator
            {
                return $this->mockedMigrationGenerator;
            }
        };

        // Check that the "No schema changes detected" message is displayed
        $noChangesMessageShown = false;
        $terminal->method('writeLine')
            ->willReturnCallback(function ($message, $color = null) use (&$noChangesMessageShown) {
                if (strpos($message, 'No schema changes detected') !== false) {
                    $noChangesMessageShown = true;
                }
            });

        $this->assertEquals(0, $command->execute());
        $this->assertTrue($noChangesMessageShown, "The 'No schema changes detected' message was not displayed");
        $this->assertEmpty(glob($this->migrationsDirectory . '/*'));
    }

    public function testExecuteWithError(): void
    {
        // Fresh terminal stub, isolated to this test
        $terminal = $this->createStub(Terminal::class);

        // Non-existent migrations directory, to trigger an error
        $invalidDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'invalid_migrations_dir';
        if (is_dir($invalidDir)) {
            rmdir($invalidDir);
        }

        $command = new MigrationGenerateCommand(
            $terminal,
            $this->entityManager,
            $invalidDir
        );

        // Check that the error message is displayed
        $errorMessageShown = false;
        $terminal->method('writeLine')
            ->willReturnCallback(function ($message, $color = null) use (&$errorMessageShown) {
                if (strpos($message, 'Error:') !== false && $color === 'red') {
                    $errorMessageShown = true;
                }
            });

        $this->assertEquals(1, $command->execute());
        $this->assertTrue($errorMessageShown, "The error message was not displayed");
    }

    /**
     * @throws Exception
     */
    public function testExecuteWithInvalidDateFormat(): void
    {
        // Fresh terminal stub, isolated to this test
        $terminal = $this->createStub(Terminal::class);

        $command = new MigrationGenerateCommand(
            $terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        // Check that the error message is displayed in red
        $errorMessageShown = false;
        $terminal->method('writeLine')
            ->willReturnCallback(function ($message, $color = null) use (&$errorMessageShown) {
                if (strpos($message, 'Error:') !== false && $color === 'red') {
                    $errorMessageShown = true;
                }
            });

        // Run with an invalid date format
        $this->assertEquals(1, $command->execute(['invalid-date-format']));
        $this->assertTrue($errorMessageShown, "The error message was not displayed");
    }

    /**
     * The command declares a name and a description
     */
    public function testCommandHasNameAndDescription(): void
    {
        $command = new MigrationGenerateCommand(
            $this->terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        $reflectionClass = new \ReflectionClass($command);

        $nameProperty = $reflectionClass->getProperty('name');
        $this->assertEquals('migration:generate', $nameProperty->getValue($command));

        $descriptionProperty = $reflectionClass->getProperty('description');
        $this->assertEquals('Generates a new migration from entity definitions', $descriptionProperty->getValue($command));
    }

    /**
     * @throws Exception
     */
    public function testExecuteWithoutProvidedDate(): void
    {
        // Fresh terminal stub, isolated to this test
        $terminal = $this->createStub(Terminal::class);

        $command = new MigrationGenerateCommand(
            $terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        // No exact expectation on the number of calls
        $terminal->method('writeLine');

        $this->assertEquals(0, $command->execute());

        // Check that a migration file was created (its name contains the current date)
        $files = glob($this->migrationsDirectory . '/*');
        $this->assertNotEmpty($files, 'A migration file should be created');
        $this->assertMatchesRegularExpression('/Migration\d{12}\.php/', basename($files[0]));
    }

    /**
     * An exception thrown while generating the migration
     * @throws Exception
     */
    public function testExecuteWithRuntimeExceptionFromGenerator(): void
    {
        // Fresh terminal stub, isolated to this test
        $terminal = $this->createStub(Terminal::class);

        // MigrationGenerator mock that throws an exception
        $migrationGenerator = $this->getMockBuilder(MigrationGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $migrationGenerator->expects($this->once())->method('generateMigration')
            ->willThrowException(new RuntimeException('Test exception'));

        // Anonymous subclass injecting the mock into the command
        $command = new class($terminal, $this->entityManager, $this->migrationsDirectory, $migrationGenerator) extends MigrationGenerateCommand {
            private MigrationGenerator $mockedGenerator;

            public function __construct(Terminal $terminal, $entityManager, $migrationsDirectory, $generator)
            {
                parent::__construct($terminal, $entityManager, $migrationsDirectory);
                $this->mockedGenerator = $generator;
            }

            protected function createMigrationGenerator($schemaComparer, $migrationsDirectory): MigrationGenerator
            {
                return $this->mockedGenerator;
            }
        };

        // Check that the specific error message is displayed
        $errorMessageShown = false;
        $terminal->method('writeLine')
            ->willReturnCallback(function ($message, $color = null) use (&$errorMessageShown) {
                if (strpos($message, 'Error: Test exception') !== false && $color === 'red') {
                    $errorMessageShown = true;
                }
            });

        $this->assertEquals(1, $command->execute());
        $this->assertTrue($errorMessageShown, "The expected error message was not displayed");
    }

    /**
     * Behaviour when MigrationGenerator is created inside execute()
     */
    public function testCreateMigrationGenerator(): void
    {
        // Expose createMigrationGenerator publicly to test it
        $command = new class($this->terminal, $this->entityManager, $this->migrationsDirectory) extends MigrationGenerateCommand {
            public function exposedCreateMigrationGenerator($schemaComparer, $migrationsDirectory): MigrationGenerator
            {
                return parent::createMigrationGenerator($schemaComparer, $migrationsDirectory);
            }
        };

        $schemaComparer = $this->createStub(SchemaComparer::class);
        $result = $command->exposedCreateMigrationGenerator($schemaComparer, $this->migrationsDirectory);

        $this->assertInstanceOf(MigrationGenerator::class, $result);
    }
}
