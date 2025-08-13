# Migration Tools

This guide presents CLI tools and schema comparison features for managing MulerTech Database migrations.

## CLI Commands

MulerTech Database provides 3 CLI commands via the MTerm framework.

### `migration:generate`

Generates a new migration based on differences between entities and current schema.

```bash
php bin/console migration:generate
```

**With custom timestamp:**
```bash
php bin/console migration:generate 202412131200
```

**How it works:**
- Compares current schema with defined entities
- Automatically generates migration SQL code
- Creates a `Migration{timestamp}.php` file in migrations directory
- Returns 0 if successful, 1 if error

**Output:**
```
Generating a migration from entity definitions...
Migration successfully generated: Migration202412131200.php
```

If no differences detected:
```
No schema changes detected, no migration generated.
```

### `migration:run`

Executes all pending migrations.

```bash
php bin/console migration:run
```

**Dry-run mode:**
```bash
php bin/console migration:run --dry-run
```

**How it works:**
- Shows list of pending migrations
- Asks for confirmation before execution (except in dry-run mode)
- Executes migrations in chronological order
- Records execution in tracking table

**Example execution:**
```
Running pending migrations...
3 pending migration(s):
 - 202412131200
 - 202412131300
 - 202412131400
Do you want to run these migrations? (y/n): y
3 migration(s) successfully executed.
```

### `migration:rollback`

Rolls back the last executed migration.

```bash
php bin/console migration:rollback
```

**Dry-run mode:**
```bash
php bin/console migration:rollback --dry-run
```

**How it works:**
- Identifies the last executed migration
- Asks for confirmation before rollback
- Executes the migration's `down()` method
- Updates migration tracking table

**Example execution:**
```
Rolling back the last migration...
Last executed migration: 202412131400
Do you want to rollback this migration? (y/n): y
Migration 202412131400 successfully rolled back.
```

## Command Configuration

### Command Setup

```php
<?php
// bin/console

require_once 'vendor/autoload.php';

use MulerTech\MTerm\Core\Terminal;
use MulerTech\Database\Schema\Migration\Command\MigrationGenerateCommand;
use MulerTech\Database\Schema\Migration\Command\MigrationRunCommand;
use MulerTech\Database\Schema\Migration\Command\MigrationRollbackCommand;
use MulerTech\Database\Schema\Migration\MigrationManager;

// EntityManager configuration
$entityManager = // ... your configuration

// MTerm Terminal
$terminal = new Terminal();

// MigrationManager
$migrationManager = new MigrationManager($entityManager, '/path/to/migrations');

// Register commands
$commands = [
    new MigrationGenerateCommand($terminal, $entityManager, '/path/to/migrations'),
    new MigrationRunCommand($terminal, $migrationManager),
    new MigrationRollbackCommand($terminal, $migrationManager)
];

// Execution
foreach ($commands as $command) {
    if ($command->name === $argv[1] ?? '') {
        exit($command->execute(array_slice($argv, 2)));
    }
}

echo "Available commands:\n";
foreach ($commands as $command) {
    echo "  {$command->name} - {$command->description}\n";
}
```

## Schema Comparison

Schema comparison allows automatic detection of differences between the current database state and defined entities.

### SchemaComparer API

#### Basic Usage

```php
use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Information\InformationSchema;

// Initialization
$informationSchema = new InformationSchema($entityManager->getEmEngine());
$schemaComparer = new SchemaComparer(
    $informationSchema,
    $entityManager->getMetadataRegistry(),
    'my_database'
);

// Compare schema
$diff = $schemaComparer->compare();

// Check for differences
if ($diff->hasDifferences()) {
    echo "Schema differences detected\n";
} else {
    echo "Schema is up to date\n";
}
```

### Types of Differences

#### Tables to Create

```php
// Get tables that need to be created
$tablesToCreate = $diff->getTablesToCreate();

foreach ($tablesToCreate as $tableName => $entityClass) {
    echo "Table to create: $tableName (entity: $entityClass)\n";
}
```

#### Tables to Drop

```php
// Get tables that need to be dropped
$tablesToDrop = $diff->getTablesToDrop();

foreach ($tablesToDrop as $tableName) {
    echo "Table to drop: $tableName\n";
}
```

#### Columns to Add

```php
// Get columns to add per table
$columnsToAdd = $diff->getColumnsToAdd();

foreach ($columnsToAdd as $tableName => $columns) {
    echo "Table: $tableName\n";
    foreach ($columns as $columnName => $definition) {
        echo "  + Column to add: $columnName\n";
        echo "    Type: {$definition['COLUMN_TYPE']}\n";
        echo "    Nullable: {$definition['IS_NULLABLE']}\n";
        if ($definition['COLUMN_DEFAULT'] !== null) {
            echo "    Default: {$definition['COLUMN_DEFAULT']}\n";
        }
    }
}
```

#### Columns to Modify

```php
// Get columns to modify
$columnsToModify = $diff->getColumnsToModify();

foreach ($columnsToModify as $tableName => $columns) {
    echo "Table: $tableName\n";
    foreach ($columns as $columnName => $changes) {
        echo "  ~ Column to modify: $columnName\n";
        
        if (isset($changes['COLUMN_TYPE'])) {
            echo "    Type: {$changes['COLUMN_TYPE']['from']} → {$changes['COLUMN_TYPE']['to']}\n";
        }
        
        if (isset($changes['IS_NULLABLE'])) {
            echo "    Nullable: {$changes['IS_NULLABLE']['from']} → {$changes['IS_NULLABLE']['to']}\n";
        }
        
        if (isset($changes['COLUMN_DEFAULT'])) {
            $from = $changes['COLUMN_DEFAULT']['from'] ?? 'NULL';
            $to = $changes['COLUMN_DEFAULT']['to'] ?? 'NULL';
            echo "    Default: $from → $to\n";
        }
    }
}
```

#### Foreign Keys

```php
// Foreign keys to add
$foreignKeysToAdd = $diff->getForeignKeysToAdd();

foreach ($foreignKeysToAdd as $tableName => $foreignKeys) {
    echo "Table: $tableName\n";
    foreach ($foreignKeys as $constraintName => $definition) {
        echo "  + FK to add: $constraintName\n";
        echo "    Column: {$definition['COLUMN_NAME']}\n";
        echo "    Reference: {$definition['REFERENCED_TABLE_NAME']}.{$definition['REFERENCED_COLUMN_NAME']}\n";
        echo "    DELETE: {$definition['DELETE_RULE']->value}\n";
        echo "    UPDATE: {$definition['UPDATE_RULE']->value}\n";
    }
}

// Foreign keys to drop
$foreignKeysToDrop = $diff->getForeignKeysToDrop();

foreach ($foreignKeysToDrop as $tableName => $constraintNames) {
    echo "Table: $tableName\n";
    foreach ($constraintNames as $constraintName) {
        echo "  - FK to drop: $constraintName\n";
    }
}
```

## Complete Difference Analysis

```php
function analyzeSchemaChanges(SchemaDifference $diff): void
{
    echo "=== SCHEMA DIFFERENCE ANALYSIS ===\n\n";
    
    // Summary
    $tablesToCreate = count($diff->getTablesToCreate());
    $tablesToDrop = count($diff->getTablesToDrop());
    $columnsToAdd = array_sum(array_map('count', $diff->getColumnsToAdd()));
    $columnsToModify = array_sum(array_map('count', $diff->getColumnsToModify()));
    $columnsToDrop = array_sum(array_map('count', $diff->getColumnsToDrop()));
    $foreignKeysToAdd = array_sum(array_map('count', $diff->getForeignKeysToAdd()));
    $foreignKeysToDrop = array_sum(array_map('count', $diff->getForeignKeysToDrop()));
    
    echo "Summary:\n";
    echo "- Tables to create: $tablesToCreate\n";
    echo "- Tables to drop: $tablesToDrop\n";
    echo "- Columns to add: $columnsToAdd\n";
    echo "- Columns to modify: $columnsToModify\n";
    echo "- Columns to drop: $columnsToDrop\n";
    echo "- Foreign keys to add: $foreignKeysToAdd\n";
    echo "- Foreign keys to drop: $foreignKeysToDrop\n\n";
    
    // New tables details
    if ($tablesToCreate > 0) {
        echo "=== NEW TABLES ===\n";
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            echo "• $tableName ($entityClass)\n";
        }
        echo "\n";
    }
    
    // Tables to drop details (WARNING)
    if ($tablesToDrop > 0) {
        echo "=== ⚠️  TABLES TO DROP (DESTRUCTIVE) ===\n";
        foreach ($diff->getTablesToDrop() as $tableName) {
            echo "• $tableName\n";
        }
        echo "\n";
    }
    
    // Column changes
    foreach ($diff->getColumnsToAdd() as $tableName => $columns) {
        echo "=== NEW COLUMNS: $tableName ===\n";
        foreach ($columns as $columnName => $definition) {
            echo "• $columnName : {$definition['COLUMN_TYPE']}";
            if ($definition['IS_NULLABLE'] === 'NO') {
                echo " NOT NULL";
            }
            if ($definition['COLUMN_DEFAULT'] !== null) {
                echo " DEFAULT {$definition['COLUMN_DEFAULT']}";
            }
            echo "\n";
        }
        echo "\n";
    }
}
```

## Useful Scripts

### Complete Check Script

```php
#!/usr/bin/env php
<?php
// scripts/check-schema.php

require_once 'vendor/autoload.php';

use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Information\InformationSchema;
use MulerTech\Database\Database\Interface\DatabaseParameterParser;

// EntityManager configuration
$entityManager = /* your configuration */;

try {
    // Initialize comparer
    $informationSchema = new InformationSchema($entityManager->getEmEngine());
    $dbParameters = new DatabaseParameterParser()->parseParameters();
    
    if (!is_string($dbParameters['dbname'])) {
        throw new RuntimeException('Database name must be a string');
    }
    
    $schemaComparer = new SchemaComparer(
        $informationSchema,
        $entityManager->getMetadataRegistry(),
        $dbParameters['dbname']
    );
    
    // Compare
    echo "Comparing schema...\n";
    $diff = $schemaComparer->compare();
    
    if (!$diff->hasDifferences()) {
        echo "✅ Schema is up to date!\n";
        exit(0);
    }
    
    echo "❌ Schema differences detected!\n\n";
    
    // Show differences
    analyzeSchemaChanges($diff);
    
    echo "Suggested commands:\n";
    echo "  php bin/console migration:generate\n";
    echo "  php bin/console migration:run\n";
    
    exit(1);
    
} catch (Exception $e) {
    echo "Error during comparison: " . $e->getMessage() . "\n";
    exit(1);
}
```

### Development Workflow

```bash
# 1. Modify your entities
# 2. Check differences
php scripts/check-schema.php

# 3. Generate migration
php bin/console migration:generate

# 4. Check generated file
cat src/Migrations/Migration202412131200.php

# 5. Test migration
php bin/console migration:run --dry-run

# 6. Execute migration
php bin/console migration:run

# 7. If problems, rollback
php bin/console migration:rollback
```

## Managing Destructive Changes

### Automatic Detection

```php
// In a pre-deployment script
$diff = $schemaComparer->compare();

if ($diff->hasDifferences()) {
    $destructiveChanges = [];
    
    // Check table drops
    if (!empty($diff->getTablesToDrop())) {
        $destructiveChanges[] = "Table drops: " . implode(', ', $diff->getTablesToDrop());
    }
    
    // Check column drops
    foreach ($diff->getColumnsToDrop() as $tableName => $columns) {
        $destructiveChanges[] = "Column drops in $tableName: " . implode(', ', $columns);
    }
    
    if (!empty($destructiveChanges)) {
        echo "⚠️  DESTRUCTIVE CHANGES DETECTED:\n";
        foreach ($destructiveChanges as $change) {
            echo "  • $change\n";
        }
        echo "\n";
        
        $confirm = readline("Continue despite destructive changes? (yes/no): ");
        if ($confirm !== 'yes') {
            echo "Operation cancelled\n";
            exit(1);
        }
    }
}
```

### Ignoring System Tables

The system automatically ignores the `migration_history` table, but you can customize this:

```php
class CustomSchemaComparer extends SchemaComparer
{
    protected function shouldIgnoreTable(string $tableName): bool
    {
        $ignoredTables = [
            'migration_history',
            'cache_entries',
            'sessions',
            'logs'
        ];
        
        return in_array($tableName, $ignoredTables);
    }
}
```

## Error Handling

### Command Errors

**Migration already executed:**
```
No pending migrations.
```

**No migration to rollback:**
```
No executed migrations to rollback.
```

**Generation error:**
```
Error: Migration directory does not exist: /path/to/migrations
```

### Troubleshooting

1. **Check migrations directory:**
```bash
ls -la src/Migrations/
```

2. **Check tracking table:**
```sql
SELECT * FROM migration_history ORDER BY executed_at DESC;
```

3. **Check database connection:**
```bash
php -r "
$entityManager = /* your config */;
try {
    $entityManager->getEmEngine()->getConnection()->query('SELECT 1');
    echo 'Connection OK';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
"
```

---

**See also:**
- [Migrations](migrations.md) - Complete migration guide
