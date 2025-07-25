<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use MulerTech\Database\Mapping\DbMappingInterface;
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
     * @param DbMappingInterface $dbMapping
     * @param string $migrationsDirectory
     */
    public function __construct(
        private readonly SchemaComparer $schemaComparer,
        private readonly DbMappingInterface $dbMapping,
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
                $columnName = $foreignKeyInfo['COLUMN_NAME'];
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
     * @throws ReflectionException
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

            $code[] = $this->generateCreateTableStatement($tableName);
            unset($columnsToAdd[$tableName]);
        }

        // Add new columns
        foreach ($columnsToAdd as $tableName => $columns) {
            foreach ($columns as $columnName => $columnDefinition) {
                $columnType = $columnDefinition['COLUMN_TYPE'];
                $columnExtra = is_string($columnDefinition['EXTRA'] ?? null)
                    ? $columnDefinition['EXTRA']
                    : null;

                $code[] = $this->generateAlterTableStatement(
                    $tableName,
                    $columnType,
                    $columnName,
                    $columnDefinition['IS_NULLABLE'] === 'YES',
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
                $hasColumnTypeFrom = isset($differences['COLUMN_TYPE']['from']);
                $hasNullableFrom = isset($differences['IS_NULLABLE']['from']);
                $hasDefaultFrom = isset($differences['COLUMN_DEFAULT']['from']);

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
     * @return string
     * @throws ReflectionException
     */
    private function generateCreateTableStatement(string $tableName): string
    {
        // Find the entity class for this table
        $entityClass = null;
        foreach ($this->dbMapping->getEntities() as $entity) {
            if ($this->dbMapping->getTableName($entity) === $tableName) {
                $entityClass = $entity;
                break;
            }
        }

        if (!$entityClass) {
            throw new RuntimeException("Could not find entity class for table '$tableName'");
        }

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->createTable("' . $tableName . '");';

        // Get all columns for this entity using DbMapping
        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            $columnDefinitionCode = $this->generateColumnDefinitionFromMapping($entityClass, $property, $columnName);
            $code[] = '        ' . $columnDefinitionCode;

            // Add primary key if needed
            $columnKey = $this->dbMapping->getColumnKey($entityClass, $property);
            if ($columnKey === 'PRI') {
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
     * Generate column definition code using DbMapping
     *
     * @param class-string $entityClass
     * @param string $property
     * @param string $columnName
     * @return string
     * @throws ReflectionException
     */
    private function generateColumnDefinitionFromMapping(string $entityClass, string $property, string $columnName): string
    {
        $code = '$tableDefinition->column("' . $columnName . '")';

        // Get column type definition directly from DbMapping
        $columnTypeDefinition = $this->dbMapping->getColumnTypeDefinition($entityClass, $property);
        if ($columnTypeDefinition) {
            // Convert SQL type to SchemaBuilder method call
            $code .= $this->convertSqlTypeToBuilderMethod($columnTypeDefinition);
        } else {
            $code .= '->string()';
        }

        // Add nullable constraint
        $isNullable = $this->dbMapping->isNullable($entityClass, $property);
        if ($isNullable === false) {
            $code .= '->notNull()';
        }

        // Add default value
        $columnDefault = $this->dbMapping->getColumnDefault($entityClass, $property);
        if ($columnDefault !== null && $columnDefault !== '') {
            $code .= '->default("' . addslashes($columnDefault) . '")';
        }

        // Add extra attributes (like auto_increment)
        $columnExtra = $this->dbMapping->getExtra($entityClass, $property);
        if ($columnExtra && str_contains($columnExtra, 'auto_increment')) {
            $code .= '->autoIncrement()';
        }

        return $code . ';';
    }

    /**
     * Convert SQL type definition to SchemaBuilder method call
     * Uses the same conversion logic as ColumnType for consistency
     *
     * @param string $sqlType
     * @return string
     */
    private function convertSqlTypeToBuilderMethod(string $sqlType): string
    {
        // Basic integer types
        if (preg_match('/^(tiny|small|medium|big)?int(\(\d+\))?\s*(unsigned)?/i', $sqlType, $matches)) {
            $size = strtolower($matches[1] ?? '');
            $unsigned = isset($matches[3]);

            $method = match($size) {
                'tiny' => '->tinyInt()',
                'small' => '->smallInt()',
                'medium' => '->mediumInt()',
                'big' => '->bigInteger()',
                default => '->integer()'
            };

            return $unsigned ? $method . '->unsigned()' : $method;
        }

        // String types
        if (preg_match('/^varchar\((\d+)\)/i', $sqlType, $matches)) {
            return '->string(' . $matches[1] . ')';
        }
        if (preg_match('/^char\((\d+)\)/i', $sqlType, $matches)) {
            return '->char(' . $matches[1] . ')';
        }

        // Decimal and floating point types
        if (preg_match('/^decimal\((\d+),(\d+)\)/i', $sqlType, $matches)) {
            return '->decimal(' . $matches[1] . ', ' . $matches[2] . ')';
        }
        if (preg_match('/^float\((\d+),(\d+)\)/i', $sqlType, $matches)) {
            return '->float(' . $matches[1] . ', ' . $matches[2] . ')';
        }

        // Double precision
        if (stripos($sqlType, 'double') === 0) {
            return '->double()';
        }

        // Binary types
        if (preg_match('/^binary\((\d+)\)/i', $sqlType, $matches)) {
            return '->binary(' . $matches[1] . ')';
        }
        if (preg_match('/^varbinary\((\d+)\)/i', $sqlType, $matches)) {
            return '->varbinary(' . $matches[1] . ')';
        }

        // BLOB types
        $blobTypes = [
            'tinyblob' => '->tinyBlob()',
            'mediumblob' => '->mediumBlob()',
            'longblob' => '->longBlob()',
            'blob' => '->blob()',
        ];
        foreach ($blobTypes as $type => $method) {
            if (stripos($sqlType, $type) === 0) {
                return $method;
            }
        }

        // Text types
        $textTypes = [
            'tinytext' => '->tinyText()',
            'mediumtext' => '->mediumText()',
            'longtext' => '->longText()',
            'text' => '->text()',
        ];
        foreach ($textTypes as $type => $method) {
            if (stripos($sqlType, $type) === 0) {
                return $method;
            }
        }

        // Date/time types
        $dateTimeTypes = [
            'datetime' => '->datetime()',
            'timestamp' => '->timestamp()',
            'date' => '->date()',
            'time' => '->time()',
            'year' => '->year()',
        ];
        foreach ($dateTimeTypes as $type => $method) {
            if (stripos($sqlType, $type) === 0) {
                return $method;
            }
        }

        // Boolean
        if (stripos($sqlType, 'boolean') === 0 || stripos($sqlType, 'bool') === 0) {
            return '->boolean()';
        }

        // JSON
        if (stripos($sqlType, 'json') === 0) {
            return '->json()';
        }

        // ENUM
        if (preg_match('/^enum\((.*)\)/i', $sqlType, $matches)) {
            $enumValues = $this->parseEnumSetValues($matches[1]);
            return '->enum([' . implode(', ', array_map(static fn ($v) => "'" . addslashes($v) . "'", $enumValues)) . '])';
        }

        // SET
        if (preg_match('/^set\((.*)\)/i', $sqlType, $matches)) {
            $setValues = $this->parseEnumSetValues($matches[1]);
            return '->set([' . implode(', ', array_map(static fn ($v) => "'" . addslashes($v) . "'", $setValues)) . '])';
        }

        // Geometry types
        $geometryTypes = [
            'geometry' => '->geometry()',
            'point' => '->point()',
            'linestring' => '->lineString()',
            'polygon' => '->polygon()',
            'multipoint' => '->multiPoint()',
            'multilinestring' => '->multiLineString()',
            'multipolygon' => '->multiPolygon()',
            'geometrycollection' => '->geometryCollection()',
        ];
        foreach ($geometryTypes as $type => $method) {
            if (stripos($sqlType, $type) === 0) {
                return $method;
            }
        }

        // Default fallback
        return '->string()';
    }

    /**
     * Generate code to alter a table and add/modify a column
     *
     * @param string $tableName
     * @param string|null $columnType
     * @param string $columnName
     * @param bool $isNullable
     * @param string|null $columnDefault
     * @param string|null $columnExtra
     * @return string
     */
    private function generateAlterTableStatement(
        string $tableName,
        ?string $columnType,
        string $columnName,
        bool $isNullable,
        ?string $columnDefault,
        ?string $columnExtra
    ): string {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';

        $columnDefinitionCode = $this->generateColumnDefinitionFromType(
            $columnType,
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );

        $code[] = '        ' . $columnDefinitionCode;
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate column definition code from raw type information
     *
     * @param string|null $columnType
     * @param string $columnName
     * @param bool $isNullable
     * @param string|null $columnDefault
     * @param string|null $columnExtra
     * @return string
     */
    private function generateColumnDefinitionFromType(
        ?string $columnType,
        string $columnName,
        bool $isNullable,
        ?string $columnDefault,
        ?string $columnExtra
    ): string {
        $code = '$tableDefinition->column("' . $columnName . '")';

        // Convert SQL type to SchemaBuilder method call
        if ($columnType) {
            $code .= $this->convertSqlTypeToBuilderMethod($columnType);
        } else {
            $code .= '->string()';
        }

        // Add nullable constraint
        if (!$isNullable) {
            $code .= '->notNull()';
        }

        // Add default value
        if ($columnDefault !== null && $columnDefault !== '') {
            $code .= '->default("' . addslashes($columnDefault) . '")';
        }

        // Add extra attributes (like auto_increment)
        if ($columnExtra && str_contains($columnExtra, 'auto_increment')) {
            $code .= '->autoIncrement()';
        }

        return $code . ';';
    }

    /**
     * Generate code to modify a column
     *
     * @param string $tableName
     * @param string $columnName
     * @param array{
     *     COLUMN_TYPE?: array{from: string|null, to: string|null},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *     EXTRA?: array{from: string|null, to: string|null}
     * } $differences
     * @return string
     */
    private function generateModifyColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $columnType = null;
        $isNullable = true;
        $columnDefault = null;
        $columnExtra = null;

        if (isset($differences['COLUMN_TYPE'])) {
            $columnType = $differences['COLUMN_TYPE']['to'] ?? 'VARCHAR(255)';
        }

        if (isset($differences['IS_NULLABLE'])) {
            $isNullable = $differences['IS_NULLABLE']['to'] === 'YES';
        }

        if (isset($differences['COLUMN_DEFAULT'])) {
            $columnDefault = $differences['COLUMN_DEFAULT']['to'];
        }

        if (isset($differences['EXTRA'])) {
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
     * @param array{
     *     COLUMN_TYPE?: array{from: string|null, to: string|null},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *     EXTRA?: array{from: string|null, to: string|null}
     * } $differences
     * @return string
     */
    private function generateRestoreColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $columnType = null;
        $isNullable = true;
        $columnDefault = null;
        $columnExtra = null;

        if (isset($differences['COLUMN_TYPE'])) {
            $columnType = $differences['COLUMN_TYPE']['from'] ?? 'VARCHAR(255)';
        }

        if (isset($differences['IS_NULLABLE'])) {
            $isNullable = $differences['IS_NULLABLE']['from'] === 'YES';
        }

        if (isset($differences['COLUMN_DEFAULT'])) {
            $columnDefault = $differences['COLUMN_DEFAULT']['from'];
        }

        if (isset($differences['EXTRA'])) {
            $columnExtra = is_string($differences['EXTRA']['from']) ? $differences['EXTRA']['from'] : null;
        }

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';

        $columnDefinitionCode = $this->generateColumnDefinitionFromType(
            is_string($columnType) ? $columnType : 'VARCHAR(255)',
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );

        $code[] = '        ' . $columnDefinitionCode;
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
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
     * @param array{
     *        COLUMN_NAME: string,
     *        REFERENCED_TABLE_NAME: string|null,
     *        REFERENCED_COLUMN_NAME: string|null,
     *        DELETE_RULE: string|null,
     *        UPDATE_RULE: string|null
     *        } $foreignKeyInfo
     * @return string
     */
    private function generateAddForeignKeyStatement(string $tableName, string $constraintName, array $foreignKeyInfo): string
    {
        $deleteRule = $foreignKeyInfo['DELETE_RULE'] ?? null;
        $updateRule = $foreignKeyInfo['UPDATE_RULE'] ?? null;

        // Get string values for referential actions
        $onDeleteRule = is_string($deleteRule)
            ? ReferentialAction::from($deleteRule)->toEnumCallString()
            : ReferentialAction::CASCADE->toEnumCallString();
        $onUpdateRule = is_string($updateRule)
            ? ReferentialAction::from($updateRule)->toEnumCallString()
            : ReferentialAction::CASCADE->toEnumCallString();

        $columnName = $foreignKeyInfo['COLUMN_NAME'];
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
}
