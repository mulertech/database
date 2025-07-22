<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use MulerTech\Database\Schema\Types\ReferentialAction;
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
                $columnType = is_string($columnDefinition['COLUMN_TYPE'] ?? null)
                    ? $columnDefinition['COLUMN_TYPE']
                    : 'VARCHAR(255)';
                $columnExtra = is_string($columnDefinition['EXTRA'] ?? null)
                    ? $columnDefinition['EXTRA']
                    : null;

                $code[] = $this->generateAlterTableStatement(
                    $tableName,
                    $columnType,
                    $columnName,
                    ($columnDefinition['IS_NULLABLE'] ?? 'YES') === 'YES',
                    $columnDefinition['COLUMN_DEFAULT'] ?? null,
                    $columnExtra
                );
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
                $hasColumnTypeFrom = is_array($differences['COLUMN_TYPE'] ?? null) && isset($differences['COLUMN_TYPE']['from']);
                $hasNullableFrom = is_array($differences['IS_NULLABLE'] ?? null) && isset($differences['IS_NULLABLE']['from']);
                $hasDefaultFrom = is_array($differences['COLUMN_DEFAULT'] ?? null) && isset($differences['COLUMN_DEFAULT']['from']);

                if ($hasColumnTypeFrom || $hasNullableFrom || $hasDefaultFrom) {
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
            $columnType = is_string($columnDefinition['COLUMN_TYPE'] ?? null)
                ? $columnDefinition['COLUMN_TYPE']
                : null;
            $columnExtra = is_string($columnDefinition['EXTRA'] ?? null)
                ? $columnDefinition['EXTRA']
                : null;

            $columnDefinitionCode = $this->parseColumnType(
                $columnType,
                $columnName,
                $columnDefinition['IS_NULLABLE'] === 'YES',
                $columnDefinition['COLUMN_DEFAULT'] ?? null,
                $columnExtra
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

        if (!empty($matches[1])) {
            foreach ($matches[1] as $value) {
                // Unescape the value
                $values[] = str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
            }
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
     * Generate code to alter a table and add/modify a column
     *
     * @param string $tableName
     * @param string|null $columnType
     * @param string $columnName
     * @param bool $isNullable
     * @param mixed $columnDefault
     * @param string|null $columnExtra
     * @return string
     */
    private function generateAlterTableStatement(
        string $tableName,
        ?string $columnType,
        string $columnName,
        bool $isNullable,
        mixed $columnDefault,
        ?string $columnExtra
    ): string {
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
        $columnType = null;
        $isNullable = true;
        $columnDefault = null;
        $columnExtra = null;

        if (isset($differences['COLUMN_TYPE']) && is_array($differences['COLUMN_TYPE'])) {
            $columnType = $differences['COLUMN_TYPE']['to'] ?? 'VARCHAR(255)';
        }

        if (isset($differences['IS_NULLABLE']) && is_array($differences['IS_NULLABLE'])) {
            $isNullable = $differences['IS_NULLABLE']['to'] === 'YES';
        }

        if (isset($differences['COLUMN_DEFAULT']) && is_array($differences['COLUMN_DEFAULT'])) {
            $columnDefault = $differences['COLUMN_DEFAULT']['to'];
        }

        if (isset($differences['EXTRA']) && is_array($differences['EXTRA'])) {
            $columnExtra = is_string($differences['EXTRA']['to']) ? $differences['EXTRA']['to'] : null;
        }

        return $this->generateAlterTableStatement(
            $tableName,
            is_string($columnType) ? $columnType : 'VARCHAR(255)',
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );
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
        $columnType = null;
        $isNullable = true;
        $columnDefault = null;
        $columnExtra = null;

        if (isset($differences['COLUMN_TYPE']) && is_array($differences['COLUMN_TYPE'])) {
            $columnType = $differences['COLUMN_TYPE']['from'] ?? 'VARCHAR(255)';
        }

        if (isset($differences['IS_NULLABLE']) && is_array($differences['IS_NULLABLE'])) {
            $isNullable = $differences['IS_NULLABLE']['from'] === 'YES';
        }

        if (isset($differences['COLUMN_DEFAULT']) && is_array($differences['COLUMN_DEFAULT'])) {
            $columnDefault = $differences['COLUMN_DEFAULT']['from'];
        }

        if (isset($differences['EXTRA']) && is_array($differences['EXTRA'])) {
            $columnExtra = is_string($differences['EXTRA']['from']) ? $differences['EXTRA']['from'] : null;
        }

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$tableDefinition = $schema->alterTable("' . $tableName . '");';

        $columnDefinitionCode = $this->parseColumnType(
            is_string($columnType) ? $columnType : 'VARCHAR(255)',
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
        $deleteRule = $foreignKeyInfo['DELETE_RULE'] ?? null;
        $updateRule = $foreignKeyInfo['UPDATE_RULE'] ?? null;

        // Get string values for referential actions
        $onDeleteRule = ($deleteRule && (is_string($deleteRule) || is_int($deleteRule)))
            ? ReferentialAction::from($deleteRule)->toEnumCallString()
            : ReferentialAction::CASCADE->toEnumCallString();
        $onUpdateRule = ($updateRule && (is_string($updateRule) || is_int($updateRule)))
            ? ReferentialAction::from($updateRule)->toEnumCallString()
            : ReferentialAction::CASCADE->toEnumCallString();

        $columnName = is_string($foreignKeyInfo['COLUMN_NAME'])
            ? $foreignKeyInfo['COLUMN_NAME']
            : '';
        $referencedTableName = is_string($foreignKeyInfo['REFERENCED_TABLE_NAME'])
            ? $foreignKeyInfo['REFERENCED_TABLE_NAME']
            : '';
        $referencedColumnName = is_string($foreignKeyInfo['REFERENCED_COLUMN_NAME'])
            ? $foreignKeyInfo['REFERENCED_COLUMN_NAME']
            : '';

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';
        $code[] = '        $tableDefinition->foreignKey("' . $constraintName . '")';
        $code[] = '            ->columns("' . $columnName . '")';
        $code[] = '            ->references("' . $referencedTableName . '", "' . $referencedColumnName . '")';
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

        // Parse the column type and add the appropriate method call
        $code .= $this->getColumnTypeDefinition($columnType);

        // Add nullable constraint
        if (!$isNullable) {
            $code .= '->notNull()';
        }

        // Add default value
        $code .= $this->getColumnDefaultValue($columnDefault);

        // Add extra attributes (like auto_increment)
        $code .= $this->getColumnExtraAttributes($columnExtra);

        return $code . ';';
    }

    /**
     * Get column type definition and return the appropriate method call
     *
     * @param string|null $columnType
     * @return string
     */
    private function getColumnTypeDefinition(?string $columnType): string
    {
        if ($columnType === null || $columnType === '') {
            return '->string()';
        }

        // Try each category of column types
        return $this->parseIntegerTypes($columnType)
            ?? $this->parseStringTypes($columnType)
            ?? $this->parseDecimalTypes($columnType)
            ?? $this->parseTextTypes($columnType)
            ?? $this->parseBinaryTypes($columnType)
            ?? $this->parseDateTimeTypes($columnType)
            ?? $this->parseSpecialTypes($columnType)
            ?? $this->parseGeometryTypes($columnType)
            ?? '->string()';
    }

    /**
     * Parse integer column types
     *
     * @param string $columnType
     * @return string|null
     */
    private function parseIntegerTypes(string $columnType): ?string
    {
        $integerTypes = [
            'tinyint' => '->tinyInt()',
            'smallint' => '->smallInt()',
            'mediumint' => '->mediumInt()',
            'bigint' => '->bigInteger()',
            'int' => '->integer()',
        ];

        foreach ($integerTypes as $type => $method) {
            if (0 === stripos($columnType, $type)) {
                $code = $method;
                if (str_contains($columnType, 'unsigned')) {
                    $code .= '->unsigned()';
                }
                return $code;
            }
        }

        return null;
    }

    /**
     * Parse string column types
     *
     * @param string $columnType
     * @return string|null
     */
    private function parseStringTypes(string $columnType): ?string
    {
        if (preg_match('/^varchar\((\d+)\)/i', $columnType, $matches)) {
            return '->string(' . $matches[1] . ')';
        }

        if (preg_match('/^char\((\d+)\)/i', $columnType, $matches)) {
            return '->char(' . $matches[1] . ')';
        }

        return null;
    }

    /**
     * Parse decimal/numeric column types
     *
     * @param string $columnType
     * @return string|null
     */
    private function parseDecimalTypes(string $columnType): ?string
    {
        if (preg_match('/^decimal\((\d+),(\d+)\)/i', $columnType, $matches)) {
            return '->decimal(' . $matches[1] . ', ' . $matches[2] . ')';
        }

        if (preg_match('/^numeric\((\d+),(\d+)\)/i', $columnType, $matches)) {
            return '->numeric(' . $matches[1] . ', ' . $matches[2] . ')';
        }

        if (preg_match('/^float\((\d+),(\d+)\)/i', $columnType, $matches)) {
            return '->float(' . $matches[1] . ', ' . $matches[2] . ')';
        }

        $floatTypes = [
            'double' => '->double()',
            'real' => '->real()',
        ];

        return $floatTypes[$columnType] ?? null;
    }

    /**
     * Parse text column types
     *
     * @param string $columnType
     * @return string|null
     */
    private function parseTextTypes(string $columnType): ?string
    {
        $textTypes = [
            'tinytext' => '->tinyText()',
            'mediumtext' => '->mediumText()',
            'longtext' => '->longText()',
            'text' => '->text()',
        ];

        return $textTypes[$columnType] ?? null;
    }

    /**
     * Parse binary column types
     *
     * @param string $columnType
     * @return string|null
     */
    private function parseBinaryTypes(string $columnType): ?string
    {
        if (preg_match('/^binary\((\d+)\)/i', $columnType, $matches)) {
            return '->binary(' . $matches[1] . ')';
        }

        if (preg_match('/^varbinary\((\d+)\)/i', $columnType, $matches)) {
            return '->varbinary(' . $matches[1] . ')';
        }

        $blobTypes = [
            'tinyblob' => '->tinyBlob()',
            'mediumblob' => '->mediumBlob()',
            'longblob' => '->longBlob()',
            'blob' => '->blob()',
        ];

        return $blobTypes[$columnType] ?? null;
    }

    /**
     * Parse date/time column types
     *
     * @param string $columnType
     * @return string|null
     */
    private function parseDateTimeTypes(string $columnType): ?string
    {
        $dateTimeTypes = [
            'datetime' => '->datetime()',
            'timestamp' => '->timestamp()',
            'date' => '->date()',
            'time' => '->time()',
            'year' => '->year()',
        ];

        return $dateTimeTypes[$columnType] ?? null;
    }

    /**
     * Parse special column types (boolean, enum, set, json)
     *
     * @param string $columnType
     * @return string|null
     */
    private function parseSpecialTypes(string $columnType): ?string
    {
        if (str_contains($columnType, 'boolean') || str_contains($columnType, 'bool')) {
            // Handle boolean types
            return '->boolean()';
        }

        if (preg_match('/^enum\((.*)\)/i', $columnType, $matches)) {
            $enumValues = $this->parseEnumSetValues($matches[1]);
            return '->enum([' . implode(', ', array_map(static fn ($v) => "'" . addslashes($v) . "'", $enumValues)) . '])';
        }

        if (preg_match('/^set\((.*)\)/i', $columnType, $matches)) {
            $setValues = $this->parseEnumSetValues($matches[1]);
            return '->set([' . implode(', ', array_map(static fn ($v) => "'" . addslashes($v) . "'", $setValues)) . '])';
        }

        if (str_contains($columnType, "json")) {
            return '->json()';
        }

        return null;
    }

    /**
     * Parse geometry column types
     *
     * @param string $columnType
     * @return string|null
     */
    private function parseGeometryTypes(string $columnType): ?string
    {
        $geometryTypes = [
            'geometry' => '->geometry()',
            'point' => '->point()',
            'linestring' => '->linestring()',
            'polygon' => '->polygon()',
        ];

        return $geometryTypes[$columnType] ?? null;
    }

    /**
     * Get column default value
     *
     * @param mixed $columnDefault
     * @return string
     */
    private function getColumnDefaultValue(mixed $columnDefault): string
    {
        if ($columnDefault === null || $columnDefault === '') {
            return '';
        }

        if (is_string($columnDefault)) {
            return '->default("' . addslashes($columnDefault) . '")';
        }

        if (is_scalar($columnDefault)) {
            return '->default("' . addslashes((string)$columnDefault) . '")';
        }

        return '';
    }

    /**
     * Get column extra attributes
     *
     * @param string|null $columnExtra
     * @return string
     */
    private function getColumnExtraAttributes(?string $columnExtra): string
    {
        if ($columnExtra !== null && str_contains($columnExtra, 'auto_increment')) {
            return '->autoIncrement()';
        }

        return '';
    }
}
