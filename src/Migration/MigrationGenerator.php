<?php

declare(strict_types=1);

namespace MulerTech\Database\Migration;

use MulerTech\Database\Migration\Schema\SchemaComparer;
use MulerTech\Database\Migration\Schema\SchemaDifference;
use MulerTech\Database\Relational\Sql\Schema\ReferentialAction;
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
    /**
     * @var string Template for migration class
     */
    private const string MIGRATION_TEMPLATE = <<<'EOT'
        <?php

        use MulerTech\Database\Migration\Migration;
        use MulerTech\Database\Relational\Sql\Schema\SchemaBuilder;

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

    /**
     * @param SchemaComparer $schemaComparer
     * @param string $migrationsDirectory
     */
    public function __construct(
        private readonly SchemaComparer $schemaComparer,
        private readonly string $migrationsDirectory,
    ) {
        // Ensure migrations directory exists
        if (!is_dir($migrationsDirectory)) {
            throw new RuntimeException("Migration directory does not exist: $migrationsDirectory");
        }
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

        // Validate schema differences to ensure prerequisites are met
        $this->validateSchemaDifferences($diff);

        $upCode = $this->generateUpCode($diff);
        $downCode = $this->generateDownCode($diff);

        $migrationContent = strtr(self::MIGRATION_TEMPLATE, [
            '%date%' => $date,
            '%up_code%' => $upCode,
            '%down_code%' => $downCode,
        ]);

        $fileName = $this->migrationsDirectory . DIRECTORY_SEPARATOR . 'Migration' . $date . '.php';
        file_put_contents($fileName, $migrationContent);

        return $fileName;
    }

    /**
     * Validate schema differences to ensure all prerequisites are met
     *
     * @param SchemaDifference $diff
     * @return void
     * @throws RuntimeException If validation fails
     */
    private function validateSchemaDifferences(SchemaDifference $diff): void
    {
        // Track tables and columns that will be created
        $tablesToCreate = array_keys($diff->getTablesToCreate());
        $columnsToCreate = [];

        foreach ($diff->getColumnsToAdd() as $tableName => $columns) {
            if (!isset($columnsToCreate[$tableName])) {
                $columnsToCreate[$tableName] = [];
            }
            foreach ($columns as $columnName => $definition) {
                $columnsToCreate[$tableName][] = $columnName;
            }
        }

        // Validate foreign keys reference existing tables and columns
        foreach ($diff->getForeignKeysToAdd() as $tableName => $foreignKeys) {
            // Skip if the table itself is being created
            if (in_array($tableName, $tablesToCreate, true)) {
                continue;
            }

            foreach ($foreignKeys as $constraintName => $foreignKeyInfo) {
                $columnName = $foreignKeyInfo['COLUMN_NAME'] ?? null;
                $referencedTable = $foreignKeyInfo['REFERENCED_TABLE_NAME'] ?? null;
                $referencedColumn = $foreignKeyInfo['REFERENCED_COLUMN_NAME'] ?? null;

                if (!$columnName || !$referencedTable || !$referencedColumn) {
                    throw new RuntimeException("Foreign key '$constraintName' has incomplete definition.");
                }
            }
        }
    }

    /**
     * Generate code for the up() method
     *
     * @param SchemaDifference $diff
     * @return string
     */
    private function generateUpCode(SchemaDifference $diff): string
    {
        $code = [];

        // Drop foreign keys first to avoid constraint issues
        foreach ($diff->getForeignKeysToDrop() as $tableName => $constraintNames) {
            foreach ($constraintNames as $constraintName) {
                $code[] = $this->generateDropForeignKeyStatement($tableName, $constraintName);
            }
        }

        // Drop columns
        foreach ($diff->getColumnsToDrop() as $tableName => $columnNames) {
            foreach ($columnNames as $columnName) {
                $code[] = $this->generateDropColumnStatement($tableName, $columnName);
            }
        }

        // Create new tables
        $columnsToAdd = $diff->getColumnsToAdd();
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            if (!isset($columnsToAdd[$tableName])) {
                throw new RuntimeException("Table '$tableName' has no columns defined.");
            }

            $tableColumns = $columnsToAdd[$tableName];
            $code[] = $this->generateCreateTableStatement($tableName, $tableColumns);
            unset($columnsToAdd[$tableName]);
        }

        // Add new columns
        foreach ($columnsToAdd as $tableName => $columns) {
            foreach ($columns as $columnName => $columnDefinition) {
                $code[] = $this->generateAddColumnStatement($tableName, $columnName, $columnDefinition);
            }
        }

        // Modify columns
        foreach ($diff->getColumnsToModify() as $tableName => $columns) {
            foreach ($columns as $columnName => $differences) {
                $code[] = $this->generateModifyColumnStatement($tableName, $columnName, $differences);
            }
        }

        // Add foreign keys
        foreach ($diff->getForeignKeysToAdd() as $tableName => $foreignKeys) {
            foreach ($foreignKeys as $constraintName => $foreignKeyInfo) {
                $code[] = $this->generateAddForeignKeyStatement($tableName, $constraintName, $foreignKeyInfo);
            }
        }

        // Drop tables (last to avoid foreign key issues)
        foreach ($diff->getTablesToDrop() as $tableName) {
            $code[] = $this->generateDropTableStatement($tableName);
        }

        return implode("\n\n", array_map(static fn ($line) => "        $line", $code));
    }

    /**
     * Generate code for the down() method
     *
     * @param SchemaDifference $diff
     * @return string
     */
    private function generateDownCode(SchemaDifference $diff): string
    {
        $code = [];

        // Drop foreign keys that were added in up()
        foreach ($diff->getForeignKeysToAdd() as $tableName => $foreignKeys) {
            foreach ($foreignKeys as $constraintName => $foreignKeyInfo) {
                $code[] = $this->generateDropForeignKeyStatement($tableName, $constraintName);
            }
        }

        // Drop tables that were created in up()
        $columnsToDrop = $diff->getColumnsToAdd();
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            $code[] = $this->generateDropTableStatement($tableName);
            unset($columnsToDrop[$tableName]);
        }

        // Drop columns that were added in up()
        foreach ($columnsToDrop as $tableName => $columns) {
            foreach ($columns as $columnName => $columnDefinition) {
                $code[] = $this->generateDropColumnStatement($tableName, $columnName);
            }
        }

        // Restore columns modifications if possible
        foreach ($diff->getColumnsToModify() as $tableName => $columns) {
            foreach ($columns as $columnName => $differences) {
                if (isset($differences['COLUMN_TYPE']['from']) ||
                    isset($differences['IS_NULLABLE']['from']) ||
                    isset($differences['COLUMN_DEFAULT']['from'])) {
                    $code[] = $this->generateRestoreColumnStatement($tableName, $columnName, $differences);
                }
            }
        }

        return empty($code)
            ? '        // No rollback operations defined'
            : implode("\n\n", array_map(static fn ($line) => "        $line", $code));
    }

    /**
     * Generate code to create a table with all its columns
     *
     * @param string $tableName
     * @param array<string, array<string, mixed>> $columnsToAdd
     * @return string
     */
    private function generateCreateTableStatement(string $tableName, array $columnsToAdd): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->createTable("' . $tableName . '");';

        // Get all columns for this entity
        foreach ($columnsToAdd as $columnName => $columnDefinition) {
            // Parse column type to determine the appropriate method
            $columnDefinitionCode = $this->parseColumnType(
                $columnDefinition['COLUMN_TYPE'] ?? null,
                $columnName,
                $columnDefinition['IS_NULLABLE'] === 'YES',
                $columnDefinition['COLUMN_DEFAULT'] ?? null,
                $columnDefinition['EXTRA'] ?? null
            );
            $code[] = '    ' . $columnDefinitionCode;

            // Add primary/unique/index keys
            if ($columnDefinition['COLUMN_KEY'] === 'PRI') {
                $code[] = '        $tableDefinition->primaryKey("' . $columnName . '");';
            }
        }

        $code[] = '        $tableDefinition->engine("InnoDB")';
        $code[] = '            ->charset("utf8mb4")';
        $code[] = '            ->collation("utf8mb4_unicode_ci");';

        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Parse column type and generate appropriate column definition code
     *
     * @param string|null $columnType
     * @param string $columnName
     * @param bool $isNullable
     * @param mixed $columnDefault
     * @param string|null $columnExtra
     * @return string
     */
    private function parseColumnType(?string $columnType, string $columnName, bool $isNullable, mixed $columnDefault, ?string $columnExtra): string
    {
        $code = '    $tableDefinition->column("' . $columnName . '")';

        if ($columnType === null || $columnType === '') {
            $code .= '->string()';
        } elseif (0 === stripos($columnType, "tinyint")) {
            $code .= '->tinyInt()';
            if (str_contains($columnType, 'unsigned')) {
                $code .= '->unsigned()';
            }
        } elseif (0 === stripos($columnType, "smallint")) {
            $code .= '->smallInt()';
            if (str_contains($columnType, 'unsigned')) {
                $code .= '->unsigned()';
            }
        } elseif (0 === stripos($columnType, "mediumint")) {
            $code .= '->mediumInt()';
            if (str_contains($columnType, 'unsigned')) {
                $code .= '->unsigned()';
            }
        } elseif (0 === stripos($columnType, "bigint")) {
            $code .= '->bigInteger()';
            if (str_contains($columnType, 'unsigned')) {
                $code .= '->unsigned()';
            }
        } elseif (0 === stripos($columnType, "int")) {
            $code .= '->integer()';
            if (str_contains($columnType, 'unsigned')) {
                $code .= '->unsigned()';
            }
        } elseif (preg_match('/^varchar\((\d+)\)/i', $columnType, $matches)) {
            $code .= '->string(' . $matches[1] . ')';
        } elseif (preg_match('/^char\((\d+)\)/i', $columnType, $matches)) {
            $code .= '->char(' . $matches[1] . ')';
        } elseif (preg_match('/^decimal\((\d+),(\d+)\)/i', $columnType, $matches)) {
            $code .= '->decimal(' . $matches[1] . ', ' . $matches[2] . ')';
        } elseif (preg_match('/^numeric\((\d+),(\d+)\)/i', $columnType, $matches)) {
            $code .= '->numeric(' . $matches[1] . ', ' . $matches[2] . ')';
        } elseif (preg_match('/^float\((\d+),(\d+)\)/i', $columnType, $matches)) {
            $code .= '->float(' . $matches[1] . ', ' . $matches[2] . ')';
        } elseif (0 === stripos($columnType, "double")) {
            $code .= '->double()';
        } elseif (0 === stripos($columnType, "real")) {
            $code .= '->real()';
        } elseif (0 === stripos($columnType, "text")) {
            $code .= '->text()';
        } elseif (0 === stripos($columnType, "tinytext")) {
            $code .= '->tinyText()';
        } elseif (0 === stripos($columnType, "mediumtext")) {
            $code .= '->mediumText()';
        } elseif (0 === stripos($columnType, "longtext")) {
            $code .= '->longText()';
        } elseif (preg_match('/^binary\((\d+)\)/i', $columnType, $matches)) {
            $code .= '->binary(' . $matches[1] . ')';
        } elseif (preg_match('/^varbinary\((\d+)\)/i', $columnType, $matches)) {
            $code .= '->varbinary(' . $matches[1] . ')';
        } elseif (0 === stripos($columnType, "blob")) {
            $code .= '->blob()';
        } elseif (0 === stripos($columnType, "tinyblob")) {
            $code .= '->tinyBlob()';
        } elseif (0 === stripos($columnType, "mediumblob")) {
            $code .= '->mediumBlob()';
        } elseif (0 === stripos($columnType, "longblob")) {
            $code .= '->longBlob()';
        } elseif (0 === stripos($columnType, "datetime")) {
            $code .= '->datetime()';
        } elseif (0 === stripos($columnType, "date")) {
            $code .= '->date()';
        } elseif (0 === stripos($columnType, "timestamp")) {
            $code .= '->timestamp()';
        } elseif (0 === stripos($columnType, "time")) {
            $code .= '->time()';
        } elseif (0 === stripos($columnType, "year")) {
            $code .= '->year()';
        } elseif (preg_match('/^(boolean|bool)/i', $columnType)) {
            $code .= '->boolean()';
        } elseif (preg_match('/^enum\((.*)\)/i', $columnType, $matches)) {
            // Parse ENUM values from the string
            $enumValues = $this->parseEnumSetValues($matches[1]);
            $code .= '->enum([' . implode(', ', array_map(static fn ($v) => "'" . addslashes($v) . "'", $enumValues)) . '])';
        } elseif (preg_match('/^set\((.*)\)/i', $columnType, $matches)) {
            // Parse SET values from the string
            $setValues = $this->parseEnumSetValues($matches[1]);
            $code .= '->set([' . implode(', ', array_map(static fn ($v) => "'" . addslashes($v) . "'", $setValues)) . '])';
        } elseif (0 === stripos($columnType, "json")) {
            $code .= '->json()';
        } elseif (0 === stripos($columnType, "geometry")) {
            $code .= '->geometry()';
        } elseif (0 === stripos($columnType, "point")) {
            $code .= '->point()';
        } elseif (0 === stripos($columnType, "linestring")) {
            $code .= '->linestring()';
        } elseif (0 === stripos($columnType, "polygon")) {
            $code .= '->polygon()';
        } else {
            // Default fallback
            $code .= '->string()';
        }

        if (!$isNullable) {
            $code .= '->notNull()';
        }

        if ($columnDefault !== null && $columnDefault !== '') {
            $code .= '->default("' . addslashes($columnDefault) . '")';
        }

        if ($columnExtra !== null && str_contains($columnExtra, 'auto_increment')) {
            $code .= '->autoIncrement()';
        }

        return $code . ';';
    }

    /**
     * Parse ENUM/SET values from MySQL column definition
     *
     * @param string $valueString The content inside parentheses (e.g., "'value1','value2','value3'")
     * @return array<string>
     */
    private function parseEnumSetValues(string $valueString): array
    {
        $values = [];
        $valueString = trim($valueString);

        // Split on commas but handle escaped quotes properly
        preg_match_all("/'([^'\\\\]*(\\\\.[^'\\\\]*)*)'/", $valueString, $matches);

        foreach ($matches[1] as $match) {
            // Unescape MySQL escaped characters
            $value = str_replace(['\\\'', '\\\\'], ["'", '\\'], $match);
            $values[] = $value;
        }

        return $values;
    }

    /**
     * Generate code to drop a table
     */
    private function generateDropTableStatement(string $tableName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $sql = $schema->dropTable("' . $tableName . '");';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to add a column
     *
     * @param string $tableName
     * @param string $columnName
     * @param array<string, mixed> $columnDefinition
     * @return string
     */
    private function generateAddColumnStatement(string $tableName, string $columnName, array $columnDefinition): string
    {
        $columnType = $columnDefinition['COLUMN_TYPE'] ?? 'VARCHAR(255)';
        $isNullable = ($columnDefinition['IS_NULLABLE'] ?? 'YES') === 'YES';
        $columnDefault = $columnDefinition['COLUMN_DEFAULT'] ?? null;
        $columnExtra = $columnDefinition['EXTRA'] ?? null;

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';

        $columnDefinitionCode = $this->parseColumnType(
            $columnType,
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );

        $code[] = '    ' . $columnDefinitionCode;
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to modify a column
     *
     * @param string $tableName
     * @param string $columnName
     * @param array<string, array<string, mixed>|mixed> $differences
     * @return string
     */
    private function generateModifyColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $columnType = $differences['COLUMN_TYPE']['to'] ?? 'VARCHAR(255)';
        $isNullable = !isset($differences['IS_NULLABLE']) || $differences['IS_NULLABLE']['to'] === 'YES';
        $columnDefault = isset($differences['COLUMN_DEFAULT'])
            ? $differences['COLUMN_DEFAULT']['to']
            : null;
        $columnExtra = isset($differences['EXTRA'])
            ? $differences['EXTRA']['to']
            : null;

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';

        $columnDefinitionCode = $this->parseColumnType(
            $columnType,
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );

        $code[] = '    ' . $columnDefinitionCode;
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to restore a column to its previous state
     *
     * @param string $tableName
     * @param string $columnName
     * @param array<string, array<string, mixed>|mixed> $differences
     * @return string
     */
    private function generateRestoreColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $columnType = $differences['COLUMN_TYPE']['from'] ?? 'VARCHAR(255)';
        $isNullable = !isset($differences['IS_NULLABLE'])
            || (isset($differences['IS_NULLABLE']['from']) && $differences['IS_NULLABLE']['from'] === 'YES');
        $columnDefault = isset($differences['COLUMN_DEFAULT'])
            ? $differences['COLUMN_DEFAULT']['from']
            : null;
        $columnExtra = isset($differences['EXTRA'])
            ? $differences['EXTRA']['from']
            : null;

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$tableDefinition = $schema->alterTable("' . $tableName . '");';

        $columnDefinitionCode = $this->parseColumnType(
            $columnType,
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );

        $code[] = '    ' . $columnDefinitionCode;
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to drop a column
     */
    private function generateDropColumnStatement(string $tableName, string $columnName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '")';
        $code[] = '            ->dropColumn("' . $columnName . '");';
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to add a foreign key
     *
     * @param string $tableName
     * @param string $constraintName
     * @param array<string, mixed> $foreignKeyInfo
     * @return string
     */
    private function generateAddForeignKeyStatement(string $tableName, string $constraintName, array $foreignKeyInfo): string
    {
        $onDeleteRule = $foreignKeyInfo['DELETE_RULE']
            ? ReferentialAction::from($foreignKeyInfo['DELETE_RULE'])->toEnumCallString()
            : ReferentialAction::CASCADE->toEnumCallString();
        $onUpdateRule = $foreignKeyInfo['UPDATE_RULE']
            ? ReferentialAction::from($foreignKeyInfo['UPDATE_RULE'])->toEnumCallString()
            : ReferentialAction::CASCADE->toEnumCallString();

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';
        $code[] = '        $tableDefinition->foreignKey("' . $constraintName . '")';
        $code[] = '            ->columns("' . $foreignKeyInfo['COLUMN_NAME'] . '")';
        $code[] = '            ->references("' . $foreignKeyInfo['REFERENCED_TABLE_NAME'] . '", "' . $foreignKeyInfo['REFERENCED_COLUMN_NAME'] . '")';
        $code[] = '            ->onDelete(' . $onDeleteRule . ')';
        $code[] = '            ->onUpdate(' . $onUpdateRule . ');';
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to drop a foreign key
     */
    private function generateDropForeignKeyStatement(string $tableName, string $constraintName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';
        $code[] = '        $tableDefinition->dropForeignKey("' . $constraintName . '");';
        $code[] = '        $this->entityManager->getPdm()->exec($tableDefinition->toSql());';

        return implode("\n", $code);
    }
}
