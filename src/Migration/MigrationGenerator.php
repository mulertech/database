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
        
        // Create tables that were dropped in up()
        foreach ($diff->getTablesToDrop() as $tableName) {
            $code[] = '// Recreate dropped table ' . $tableName;
            $code[] = '$this->createQueryBuilder()';
            $code[] = '    ->insert("' . $tableName . '")';
            $code[] = '    ->execute();';
        }
        
        // Drop foreign keys that were added in up()
        foreach ($diff->getForeignKeysToAdd() as $tableName => $foreignKeys) {
            foreach ($foreignKeys as $constraintName => $foreignKeyInfo) {
                $code[] = $this->generateDropForeignKeyStatement($tableName, $constraintName);
            }
        }
        
        // Restore modified columns
        foreach ($diff->getColumnsToModify() as $tableName => $columns) {
            foreach ($columns as $columnName => $differences) {
                $code[] = '// Restore modified column ' . $tableName . '.' . $columnName;
                $code[] = '$this->createQueryBuilder()';
                $code[] = '    ->update("' . $tableName . '")';
                $code[] = '    ->execute();';
            }
        }
        
        // Drop columns that were added in up()
        foreach ($diff->getColumnsToAdd() as $tableName => $columns) {
            foreach ($columns as $columnName => $columnDefinition) {
                $code[] = $this->generateDropColumnStatement($tableName, $columnName);
            }
        }
        
        // Drop tables that were created in up()
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            $code[] = $this->generateDropTableStatement($tableName);
        }
        
        // Restore columns that were dropped in up()
        foreach ($diff->getColumnsToDrop() as $tableName => $columnNames) {
            foreach ($columnNames as $columnName) {
                $code[] = '// Restore dropped column ' . $tableName . '.' . $columnName;
                $code[] = '$this->createQueryBuilder()';
                $code[] = '    ->update("' . $tableName . '")';
                $code[] = '    ->execute();';
            }
        }
        
        // Restore foreign keys that were dropped in up()
        foreach ($diff->getForeignKeysToDrop() as $tableName => $constraintNames) {
            foreach ($constraintNames as $constraintName) {
                $code[] = '// Restore dropped foreign key ' . $constraintName;
                $code[] = '$this->createQueryBuilder()';
                $code[] = '    ->update("' . $tableName . '")';
                $code[] = '    ->execute();';
            }
        }
        
        return empty($code) 
            ? '        // No rollback operations defined'
            : implode("\n\n", array_map(fn($line) => "        $line", $code));
    }

    /**
     * Generate SQL to create a table with all its columns
     *
     * @param string $tableName
     * @param string $entityClass
     * @return string
     * @throws \ReflectionException
     */
    private function generateCreateTableStatement(string $tableName, string $entityClass): string
    {
        $columnDefinitions = [];
        $primaryKeys = [];
        $uniqueKeys = [];
        $indexes = [];

        // Get all columns for this entity
        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            $columnType = $this->dbMapping->getColumnType($entityClass, $property);
            $isNullable = $this->dbMapping->isNullable($entityClass, $property);
            $columnDefault = $this->dbMapping->getColumnDefault($entityClass, $property);
            $columnExtra = $this->dbMapping->getExtra($entityClass, $property);
            $columnKey = $this->dbMapping->getColumnKey($entityClass, $property);

            $columnDefinition = "`$columnName`";
            $columnDefinition .= $columnType === null ? '' : ' ' . $columnType;
            $columnDefinition .= $isNullable === true ? '' : ' NOT NULL';
            $columnDefinition .= $columnDefault === null ? '' : " DEFAULT '$columnDefault'";
            $columnDefinition .= $columnExtra === null ? '' : ' ' . $columnExtra;

            $columnDefinitions[] = $columnDefinition;

            // Track keys
            if ($columnKey === 'PRI') {
                $primaryKeys[] = $columnName;
            } elseif ($columnKey === 'UNI') {
                $uniqueKeys[] = $columnName;
            } elseif ($columnKey === 'MUL') {
                $indexes[] = $columnName;
            }
        }

        if (empty($columnDefinitions)) {
            throw new RuntimeException("Cannot create table '$tableName': No columns defined in entity '$entityClass'.");
        }

        // Add primary key constraint if any
        if (!empty($primaryKeys)) {
            $primaryKeyStr = implode('`, `', $primaryKeys);
            $columnDefinitions[] = "PRIMARY KEY (`$primaryKeyStr`)";
        }

        // Add unique key constraints
        foreach ($uniqueKeys as $i => $columnName) {
            $columnDefinitions[] = "UNIQUE KEY `uk_{$tableName}_{$columnName}` (`$columnName`)";
        }

        // Add indexes
        foreach ($indexes as $i => $columnName) {
            $columnDefinitions[] = "INDEX `idx_{$tableName}_{$columnName}` (`$columnName`)";
        }

        // Build create table SQL
        $columnsSql = implode(",\n            ", $columnDefinitions);

        return '$sql = "CREATE TABLE `' . $tableName . '` (' .
               "\n" .
               '            ' . $columnsSql .
               "\n" .
               ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";' .
               "\n" .
               '$this->entityManager->getPdm()->exec($sql);';
    }
    
    /**
     * Generate SQL to drop a table
     *
     * @param string $tableName
     * @return string
     */
    private function generateDropTableStatement(string $tableName): string
    {
        return '$this->entityManager->getPdm()->exec("DROP TABLE IF EXISTS `' . $tableName . '`");';
    }
    
    /**
     * Generate SQL to add a column
     *
     * @param string $tableName
     * @param string $columnName
     * @param array $columnDefinition
     * @return string
     */
    private function generateAddColumnStatement(string $tableName, string $columnName, array $columnDefinition): string
    {
        $columnType = $columnDefinition['COLUMN_TYPE'] ?? 'VARCHAR(255)';
        $nullable = ($columnDefinition['IS_NULLABLE'] ?? 'YES') === 'YES' ? 'NULL' : 'NOT NULL';
        $default = isset($columnDefinition['COLUMN_DEFAULT']) 
            ? "DEFAULT " . ($columnDefinition['COLUMN_DEFAULT'] === null ? "NULL" : "'" . $columnDefinition['COLUMN_DEFAULT'] . "'")
            : '';
            
        return '$this->entityManager->getPdm()->exec(' .
               '"ALTER TABLE `' . $tableName . '` ' .
               'ADD COLUMN `' . $columnName . '` ' . $columnType . ' ' . $nullable . ' ' . $default . '");';
    }
    
    /**
     * Generate SQL to modify a column
     *
     * @param string $tableName
     * @param string $columnName
     * @param array $differences
     * @return string
     */
    private function generateModifyColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $columnType = $differences['COLUMN_TYPE']['to'] ?? '';
        $nullable = isset($differences['IS_NULLABLE']) 
            ? (($differences['IS_NULLABLE']['to'] === 'YES') ? 'NULL' : 'NOT NULL') 
            : '';
        $default = isset($differences['COLUMN_DEFAULT']) 
            ? "DEFAULT " . ($differences['COLUMN_DEFAULT']['to'] === null ? "NULL" : "'" . $differences['COLUMN_DEFAULT']['to'] . "'")
            : '';
            
        return '$this->entityManager->getPdm()->exec(' .
               '"ALTER TABLE `' . $tableName . '` ' .
               'MODIFY COLUMN `' . $columnName . '` ' . $columnType . ' ' . $nullable . ' ' . $default . '");';
    }
    
    /**
     * Generate SQL to drop a column
     *
     * @param string $tableName
     * @param string $columnName
     * @return string
     */
    private function generateDropColumnStatement(string $tableName, string $columnName): string
    {
        return '$this->entityManager->getPdm()->exec(' .
               '"ALTER TABLE `' . $tableName . '` DROP COLUMN `' . $columnName . '`");';
    }
    
    /**
     * Generate SQL to add a foreign key
     *
     * @param string $tableName
     * @param string $constraintName
     * @param array $foreignKeyInfo
     * @return string
     */
    private function generateAddForeignKeyStatement(string $tableName, string $constraintName, array $foreignKeyInfo): string
    {
        return '$this->entityManager->getPdm()->exec(' .
               '"ALTER TABLE `' . $tableName . '` ' .
               'ADD CONSTRAINT `' . $constraintName . '` ' .
               'FOREIGN KEY (`' . $foreignKeyInfo['COLUMN_NAME'] . '`) ' .
               'REFERENCES `' . $foreignKeyInfo['REFERENCED_TABLE_NAME'] . '` ' .
               '(`' . $foreignKeyInfo['REFERENCED_COLUMN_NAME'] . '`) ' .
               'ON DELETE ' . ($foreignKeyInfo['DELETE_RULE'] ?? 'CASCADE') . ' ' .
               'ON UPDATE ' . ($foreignKeyInfo['UPDATE_RULE'] ?? 'CASCADE') . '");';
    }
    
    /**
     * Generate SQL to drop a foreign key
     *
     * @param string $tableName
     * @param string $constraintName
     * @return string
     */
    private function generateDropForeignKeyStatement(string $tableName, string $constraintName): string
    {
        return '$this->entityManager->getPdm()->exec(' .
               '"ALTER TABLE `' . $tableName . '` DROP FOREIGN KEY `' . $constraintName . '`");';
    }
}

