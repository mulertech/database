<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Schema\Diff\SchemaComparer;
use ReflectionException;
use RuntimeException;

/**
 * Class MigrationGenerator
 *
 * Generate migrations based on schema differences
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MigrationGenerator
{
    private const string MIGRATION_TEMPLATE = <<<'EOT'
        <?php

        use MulerTech\Database\Schema\Migration\Migration;
        use MulerTech\Database\Schema\Builder\SchemaBuilder;

        /**
         * Auto-generated migration
         */
        class Migration%date% extends Migration
        {
            /**
             * {@inheritdoc}
             */
            public function up(): void
            {
        %up_code%
            }

            /**
             * {@inheritdoc}
             */
            public function down(): void
            {
        %down_code%
            }
        }
        EOT;

    private readonly MigrationCodeGenerator $codeGenerator;

    /**
     * @param SchemaComparer $schemaComparer
     * @param DbMappingInterface $dbMapping
     * @param string $migrationsDirectory
     */
    public function __construct(
        private readonly SchemaComparer $schemaComparer,
        DbMappingInterface $dbMapping,
        private readonly string $migrationsDirectory,
    ) {
        // Ensure migrations directory exists
        if (!is_dir($migrationsDirectory)) {
            throw new RuntimeException("Migration directory does not exist: $migrationsDirectory");
        }

        $this->codeGenerator = new MigrationCodeGenerator($dbMapping);
    }

    /**
     * Generate a migration based on schema differences
     *
     * @return string|null Path to generated migration file or null if no changes
     * @throws ReflectionException
     */
    public function generateMigration(?string $datetime = null): ?string
    {
        if ($datetime !== null && !preg_match('/^(\d{8})(\d{4})$/', $datetime)) {
            throw new RuntimeException('Invalid datetime format. Expected: YYYYMMDDHHMM');
        }
        $date = $datetime ?? date('YmdHi');

        $diff = $this->schemaComparer->compare();

        if (!$diff->hasDifferences()) {
            return null;
        }

        $upCode = $this->codeGenerator->generateUpCode($diff);
        $downCode = $this->codeGenerator->generateDownCode($diff);

        $migrationContent = strtr(self::MIGRATION_TEMPLATE, [
            '%date%' => $date,
            '%up_code%' => $upCode,
            '%down_code%' => $downCode,
        ]);

        $fileName = $this->migrationsDirectory . DIRECTORY_SEPARATOR . 'Migration' . $date . '.php';
        file_put_contents($fileName, $migrationContent);

        return $fileName;
    }
}
