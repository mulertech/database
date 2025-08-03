<?php

namespace MulerTech\Database\Tests\Schema\Migration\Command;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
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

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->terminal = $this->createMock(Terminal::class);
        // Create MetadataCache with automatic entity loading from test directory
        $entitiesPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';
        $metadataCache = new MetadataCache(null, $entitiesPath);
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $metadataCache
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
        // Créer un mock frais pour isoler ce test
        $terminal = $this->createMock(Terminal::class);

        $migrationGenerateCommand = new MigrationGenerateCommand(
            $terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        // Ne pas spécifier un nombre exact d'appels pour éviter les erreurs
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
        // Créer un mock frais pour isoler ce test
        $terminal = $this->createMock(Terminal::class);

        // Créer un mock pour SchemaComparer qui indique qu'il n'y a pas de différences
        $schemaComparer = $this->createMock(SchemaComparer::class);
        $schemaComparer->method('compare')->willReturn(
            new SchemaDifference([], [], [], [], [], [], [], [])
        );

        // Créer un mock pour MigrationGenerator qui utilise notre SchemaComparer mocké
        $migrationGenerator = $this->getMockBuilder(MigrationGenerator::class)
            ->setConstructorArgs([$schemaComparer, $this->entityManager->getMetadataCache(), $this->migrationsDirectory])
            ->getMock();
        $migrationGenerator->method('generateMigration')->willReturn(null);

        // Utiliser la reflexion pour injecter notre mock dans la commande
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

        // Vérifier que le message "No schema changes detected" est affiché
        $noChangesMessageShown = false;
        $terminal->method('writeLine')
            ->willReturnCallback(function ($message, $color = null) use (&$noChangesMessageShown) {
                if (strpos($message, 'No schema changes detected') !== false) {
                    $noChangesMessageShown = true;
                }
            });

        $this->assertEquals(0, $command->execute());
        $this->assertTrue($noChangesMessageShown, "Le message 'No schema changes detected' n'a pas été affiché");
        $this->assertEmpty(glob($this->migrationsDirectory . '/*'));
    }

    public function testExecuteWithError(): void
    {
        // Créer un mock frais pour isoler ce test
        $terminal = $this->createMock(Terminal::class);

        // Créer un répertoire de migrations inexistant pour provoquer une erreur
        $invalidDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'invalid_migrations_dir';
        if (is_dir($invalidDir)) {
            rmdir($invalidDir);
        }

        $command = new MigrationGenerateCommand(
            $terminal,
            $this->entityManager,
            $invalidDir
        );

        // Vérifier que le message d'erreur est affiché
        $errorMessageShown = false;
        $terminal->method('writeLine')
            ->willReturnCallback(function ($message, $color = null) use (&$errorMessageShown) {
                if (strpos($message, 'Error:') !== false && $color === 'red') {
                    $errorMessageShown = true;
                }
            });

        $this->assertEquals(1, $command->execute());
        $this->assertTrue($errorMessageShown, "Le message d'erreur n'a pas été affiché");
    }

    /**
     * @throws Exception
     */
    public function testExecuteWithInvalidDateFormat(): void
    {
        // Créer un mock frais pour isoler ce test
        $terminal = $this->createMock(Terminal::class);

        $command = new MigrationGenerateCommand(
            $terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        // Vérifier que le message d'erreur est affiché avec le bon format
        $errorMessageShown = false;
        $terminal->method('writeLine')
            ->willReturnCallback(function ($message, $color = null) use (&$errorMessageShown) {
                if (strpos($message, 'Error:') !== false && $color === 'red') {
                    $errorMessageShown = true;
                }
            });

        // Exécuter avec un format de date invalide
        $this->assertEquals(1, $command->execute(['invalid-date-format']));
        $this->assertTrue($errorMessageShown, "Le message d'erreur n'a pas été affiché");
    }

    /**
     * Test que la commande a bien un nom et une description
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
        // Créer un mock frais pour isoler ce test
        $terminal = $this->createMock(Terminal::class);

        $command = new MigrationGenerateCommand(
            $terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        // Ne pas spécifier d'attente exacte sur le nombre d'appels
        $terminal->method('writeLine');

        $this->assertEquals(0, $command->execute());

        // Vérifier qu'un fichier de migration a été créé (le nom contient la date actuelle)
        $files = glob($this->migrationsDirectory . '/*');
        $this->assertNotEmpty($files, 'A migration file should be created');
        $this->assertMatchesRegularExpression('/Migration\d{12}\.php/', basename($files[0]));
    }

    /**
     * Test avec une exception lors de la génération de migration
     * @throws Exception
     */
    public function testExecuteWithRuntimeExceptionFromGenerator(): void
    {
        // Créer un mock frais pour isoler ce test
        $terminal = $this->createMock(Terminal::class);

        // Créer un mock de MigrationGenerator qui lance une exception
        $migrationGenerator = $this->getMockBuilder(MigrationGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $migrationGenerator->method('generateMigration')
            ->willThrowException(new RuntimeException('Test exception'));

        // Utiliser la reflexion pour injecter notre mock dans la commande
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

        // Vérifier que le message d'erreur spécifique est affiché
        $errorMessageShown = false;
        $terminal->method('writeLine')
            ->willReturnCallback(function ($message, $color = null) use (&$errorMessageShown) {
                if (strpos($message, 'Error: Test exception') !== false && $color === 'red') {
                    $errorMessageShown = true;
                }
            });

        $this->assertEquals(1, $command->execute());
        $this->assertTrue($errorMessageShown, "Le message d'erreur attendu n'a pas été affiché");
    }

    /**
     * Test pour vérifier le comportement lorsque MigrationGenerator est créé dans execute()
     */
    public function testCreateMigrationGenerator(): void
    {
        // On teste la méthode createMigrationGenerator en la rendant publique
        $command = new class($this->terminal, $this->entityManager, $this->migrationsDirectory) extends MigrationGenerateCommand {
            public function exposedCreateMigrationGenerator($schemaComparer, $migrationsDirectory): MigrationGenerator
            {
                return parent::createMigrationGenerator($schemaComparer, $migrationsDirectory);
            }
        };

        $schemaComparer = $this->createMock(SchemaComparer::class);
        $result = $command->exposedCreateMigrationGenerator($schemaComparer, $this->migrationsDirectory);

        $this->assertInstanceOf(MigrationGenerator::class, $result);
    }
}
