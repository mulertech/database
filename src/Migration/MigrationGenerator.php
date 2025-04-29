<?php

namespace MulerTech\Database\Migration;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Migration\Schema\SchemaComparer;
use MulerTech\Database\Migration\Schema\SchemaDifference;
use RuntimeException;

/**
 * Generate migrations based on schema differences
 * 
 * @package MulerTech\Database\Migration
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
     * @param DbMappingInterface $dbMapping
     */
    public function __construct(
        private readonly SchemaComparer $schemaComparer,
        private readonly string $migrationsDirectory,
        private readonly DbMappingInterface $dbMapping
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
     */
    public function generateMigration(?string $datetime = null): ?string
    {
        if ($datetime !== null && !preg_match('/^(\d{8})(\d{4})$/', $datetime, $matches)) {
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
            '%down_code%' => $downCode
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
        // Validate tables to create have columns
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            $columns = $this->dbMapping->getPropertiesColumns($entityClass);
            if (empty($columns)) {
                throw new RuntimeException("Cannot create table '$tableName': Entity '$entityClass' has no columns defined.");
            }
        }

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

                // Check if the column exists or will be created
                $columnWillExist = isset($columnsToCreate[$tableName]) && in_array($columnName, $columnsToCreate[$tableName], true);

                if (!$columnWillExist) {
                    throw new RuntimeException(
                        "Cannot add foreign key '$constraintName': Column '$tableName.$columnName' does not exist."
                    );
                }

                // Check if referenced table exists or will be created
                $tableWillExist = in_array($referencedTable, $tablesToCreate, true);

                if (!$tableWillExist) {
                    throw new RuntimeException(
                        "Cannot add foreign key '$constraintName': Referenced table '$referencedTable' does not exist."
                    );
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
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            $code[] = $this->generateCreateTableStatement($tableName, $entityClass);
        }
        
        // Add new columns
        foreach ($diff->getColumnsToAdd() as $tableName => $columns) {
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
        
        return implode("\n\n", array_map(fn($line) => "        $line", $code));
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

        // Drop tables that were created in up()
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            $code[] = $this->generateDropTableStatement($tableName);
        }

        // Drop foreign keys that were added in up()
        foreach ($diff->getForeignKeysToAdd() as $tableName => $foreignKeys) {
            foreach ($foreignKeys as $constraintName => $foreignKeyInfo) {
                $code[] = $this->generateDropForeignKeyStatement($tableName, $constraintName);
            }
        }

        // Drop columns that were added in up()
        foreach ($diff->getColumnsToAdd() as $tableName => $columns) {
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
            : implode("\n\n", array_map(fn($line) => "        $line", $code));
    }

    /**
     * Generate code to create a table with all its columns
     */
    private function generateCreateTableStatement(string $tableName, string $entityClass): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$tableDefinition = $schema->createTable("' . $tableName . '")';

        // Get all columns for this entity
        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            $columnType = $this->dbMapping->getColumnType($entityClass, $property);
            $isNullable = $this->dbMapping->isNullable($entityClass, $property);
            $columnDefault = $this->dbMapping->getColumnDefault($entityClass, $property);
            $columnExtra = $this->dbMapping->getExtra($entityClass, $property);
            $columnKey = $this->dbMapping->getColumnKey($entityClass, $property);

            // Parse column type to determine the appropriate method
            $columnDefinitionCode = $this->parseColumnType($columnType, $columnName, $isNullable, $columnDefault, $columnExtra);
            $code[] = '    ' . $columnDefinitionCode;

            // Add primary/unique/index keys
            if ($columnKey === 'PRI') {
                $code[] = '    ->primaryKey("' . $columnName . '")';
            }
        }

        $code[] = '    ->engine("InnoDB")';
        $code[] = '    ->charset("utf8mb4")';
        $code[] = '    ->collation("utf8mb4_unicode_ci");';

        $code[] = '$sql = $tableDefinition->toSql();';
        $code[] = '$this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Parse column type and generate appropriate column definition code
     */
    private function parseColumnType(?string $columnType, string $columnName, bool $isNullable, $columnDefault, ?string $columnExtra): string
    {
        $code = '->column("' . $columnName . '")';

        if ($columnType === null || $columnType === '') {
            $code .= '->string()';
        } elseif (preg_match('/^int/i', $columnType)) {
            $code .= '->integer()';
            if (strpos($columnType, 'unsigned') !== false) {
                $code .= '->unsigned()';
            }
        } elseif (preg_match('/^bigint/i', $columnType)) {
            $code .= '->bigInteger()';
            if (strpos($columnType, 'unsigned') !== false) {
                $code .= '->unsigned()';
            }
        } elseif (preg_match('/^varchar\((\d+)\)/i', $columnType, $matches)) {
            $code .= '->string(' . $matches[1] . ')';
        } elseif (preg_match('/^decimal\((\d+),(\d+)\)/i', $columnType, $matches)) {
            $code .= '->decimal(' . $matches[1] . ', ' . $matches[2] . ')';
        } elseif (preg_match('/^text/i', $columnType)) {
            $code .= '->text()';
        } elseif (preg_match('/^datetime/i', $columnType)) {
            $code .= '->datetime()';
        }

        if (!$isNullable) {
            $code .= '->notNull()';
        }

        if ($columnDefault !== null) {
            $code .= '->default("' . addslashes($columnDefault) . '")';
        }

        if ($columnExtra !== null && strpos($columnExtra, 'auto_increment') !== false) {
            $code .= '->autoIncrement()';
        }

        return $code;
    }

    /**
     * Generate code to drop a table
     */
    private function generateDropTableStatement(string $tableName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$sql = $schema->dropTable("' . $tableName . '");';
        $code[] = '$this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to add a column
     */
    private function generateAddColumnStatement(string $tableName, string $columnName, array $columnDefinition): string
    {
        $columnType = $columnDefinition['COLUMN_TYPE'] ?? 'VARCHAR(255)';
        $isNullable = ($columnDefinition['IS_NULLABLE'] ?? 'YES') === 'YES';
        $columnDefault = $columnDefinition['COLUMN_DEFAULT'] ?? null;
        $columnExtra = $columnDefinition['EXTRA'] ?? null;

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$tableDefinition = $schema->alterTable("' . $tableName . '")';

        $columnDefinitionCode = $this->parseColumnType(
            $columnType,
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );

        $code[] = '    ' . $columnDefinitionCode . ';';
        $code[] = '$sql = $tableDefinition->toSql();';
        $code[] = '$this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to modify a column
     */
    private function generateModifyColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $columnType = $differences['COLUMN_TYPE']['to'] ?? 'VARCHAR(255)';
        $isNullable = isset($differences['IS_NULLABLE'])
            ? ($differences['IS_NULLABLE']['to'] === 'YES')
            : true;
        $columnDefault = isset($differences['COLUMN_DEFAULT'])
            ? $differences['COLUMN_DEFAULT']['to']
            : null;
        $columnExtra = isset($differences['EXTRA'])
            ? $differences['EXTRA']['to']
            : null;

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$tableDefinition = $schema->alterTable("' . $tableName . '")';

        $columnDefinitionCode = $this->parseColumnType(
            $columnType,
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );

        $code[] = '    ' . $columnDefinitionCode . ';'; // L'altération va modifier la colonne existante
        $code[] = '$sql = $tableDefinition->toSql();';
        $code[] = '$this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to restore a column to its previous state
     */
    private function generateRestoreColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $columnType = $differences['COLUMN_TYPE']['from'] ?? 'VARCHAR(255)';
        $isNullable = isset($differences['IS_NULLABLE'])
            ? ($differences['IS_NULLABLE']['from'] === 'YES')
            : true;
        $columnDefault = isset($differences['COLUMN_DEFAULT'])
            ? $differences['COLUMN_DEFAULT']['from']
            : null;
        $columnExtra = isset($differences['EXTRA'])
            ? $differences['EXTRA']['from']
            : null;

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$tableDefinition = $schema->alterTable("' . $tableName . '")';

        $columnDefinitionCode = $this->parseColumnType(
            $columnType,
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra
        );

        $code[] = '    ' . $columnDefinitionCode . ';';
        $code[] = '$sql = $tableDefinition->toSql();';
        $code[] = '$this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to drop a column
     */
    private function generateDropColumnStatement(string $tableName, string $columnName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$tableDefinition = $schema->alterTable("' . $tableName . '")';
        $code[] = '    ->dropColumn("' . $columnName . '");';
        $code[] = '$sql = $tableDefinition->toSql();';
        $code[] = '$this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to add a foreign key
     */
    private function generateAddForeignKeyStatement(string $tableName, string $constraintName, array $foreignKeyInfo): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$tableDefinition = $schema->alterTable("' . $tableName . '")';
        $code[] = '    ->foreignKey("' . $constraintName . '")';
        $code[] = '    ->columns("' . $foreignKeyInfo['COLUMN_NAME'] . '")';
        $code[] = '    ->references("' . $foreignKeyInfo['REFERENCED_TABLE_NAME'] . '", "' . $foreignKeyInfo['REFERENCED_COLUMN_NAME'] . '")';
        $code[] = '    ->onDelete("' . ($foreignKeyInfo['DELETE_RULE'] ?? 'CASCADE') . '")';
        $code[] = '    ->onUpdate("' . ($foreignKeyInfo['UPDATE_RULE'] ?? 'CASCADE') . '");';
        $code[] = '$sql = $tableDefinition->toSql();';
        $code[] = '$this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to drop a foreign key
     */
    private function generateDropForeignKeyStatement(string $tableName, string $constraintName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '$sql = "ALTER TABLE ' . $tableName . ' DROP FOREIGN KEY ' . $constraintName . '";'; // À adapter quand SchemaBuilder supportera cette opération
        $code[] = '$this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }
}