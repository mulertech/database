# Migrations

Migrations allow you to manage database schema evolution in a controlled and reproducible way.

## Overview

A migration is a PHP file that describes modifications to apply to the database schema. Each migration has:
- A unique timestamp for execution order (format `YYYYMMDDHHMM`)
- An `up()` method to apply changes
- A `down()` method to rollback changes

## Migration Structure

### Base Class

All migrations inherit from `MulerTech\Database\Schema\Migration\Migration`:

```php
abstract class Migration
{
    abstract public function up(): void;
    abstract public function down(): void;
    
    public function getVersion(): string;
    public function createQueryBuilder(): QueryBuilder;
}
```

### Naming Convention

- Format: `Migration{YYYYMMDDHHMM}`
- Example: `Migration202412131200` (December 13, 2024, 12:00)

### Complete Migration Example

```php
<?php

declare(strict_types=1);

use MulerTech\Database\Schema\Migration\Migration;

/**
 * Add user_profiles table
 */
class Migration202412131200 extends Migration
{
    public function up(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        // Create user_profiles table
        $queryBuilder->raw("
            CREATE TABLE user_profiles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                bio TEXT,
                avatar_url VARCHAR(255),
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY UNIQ_USER_PROFILE_USER_ID (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ")->execute();
    }

    public function down(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->raw("DROP TABLE user_profiles")->execute();
    }
}
```

## Creating Migrations

### Automatic Generation

MulerTech Database can automatically generate migrations by comparing the current schema with your entities:

```php
use MulerTech\Database\Schema\Migration\MigrationGenerator;
use MulerTech\Database\Schema\Diff\SchemaComparer;

$generator = new MigrationGenerator($schemaComparer, $metadataRegistry, '/path/to/migrations');
$migrationFile = $generator->generateMigration();

if ($migrationFile) {
    echo "Migration created: " . basename($migrationFile);
} else {
    echo "No differences detected";
}
```

### Types of Operations

#### Table Creation

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    $queryBuilder->raw("
        CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_PRODUCT_NAME (name)
        )
    ")->execute();
}
```

#### Table Modification

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    // Add a column
    $queryBuilder->raw("
        ALTER TABLE users 
        ADD COLUMN email_verified_at DATETIME NULL
    ")->execute();
    
    // Modify a column
    $queryBuilder->raw("
        ALTER TABLE users 
        MODIFY COLUMN email VARCHAR(180) NOT NULL
    ")->execute();
    
    // Add an index
    $queryBuilder->raw("
        ALTER TABLE users 
        ADD INDEX IDX_USER_EMAIL_VERIFIED (email_verified_at)
    ")->execute();
}
```

#### Constraints and Foreign Keys

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    // Add a foreign key
    $queryBuilder->raw("
        ALTER TABLE orders 
        ADD CONSTRAINT FK_ORDER_USER 
        FOREIGN KEY (user_id) REFERENCES users(id) 
        ON DELETE CASCADE ON UPDATE CASCADE
    ")->execute();
    
    // Add a unique constraint
    $queryBuilder->raw("
        ALTER TABLE orders 
        ADD CONSTRAINT UNIQ_ORDER_NUMBER 
        UNIQUE (order_number)
    ")->execute();
}
```

#### Data Insertion

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    // Insert base data
    $queryBuilder->raw("
        INSERT INTO categories (name) VALUES (?)
    ")->bind(['Electronics'])->execute();
    
    $queryBuilder->raw("
        INSERT INTO categories (name) VALUES (?)
    ")->bind(['Books'])->execute();
    
    $queryBuilder->raw("
        INSERT INTO categories (name) VALUES (?)
    ")->bind(['Clothing'])->execute();
}
```

## Migration Management

### MigrationManager API

The `MigrationManager` provides a simple API for managing migrations:

```php
use MulerTech\Database\Schema\Migration\MigrationManager;

// Initialization
$migrationManager = new MigrationManager($entityManager);

// Register migrations from a directory
$migrationManager->registerMigrations('/path/to/migrations');
```

### Executing Migrations

#### Simple Migration

```php
// Execute all pending migrations
$executed = $migrationManager->migrate();
echo "Migrations executed: $executed\n";
```

#### Check Pending Migrations

```php
// Get pending migrations
$pendingMigrations = $migrationManager->getPendingMigrations();

foreach ($pendingMigrations as $migration) {
    echo "Pending: " . $migration->getVersion() . "\n";
}

if (empty($pendingMigrations)) {
    echo "No pending migrations\n";
}
```

#### Execute Specific Migration

```php
// Register individual migration
$migration = new Migration202412131200($entityManager);
$migrationManager->registerMigration($migration);

// Execute this migration if not already executed
if (!$migrationManager->isMigrationExecuted($migration)) {
    $migrationManager->executeMigration($migration);
    echo "Migration {$migration->getVersion()} executed\n";
}
```

### Rollback

#### Simple Rollback

```php
// Rollback the last executed migration
$success = $migrationManager->rollback();

if ($success) {
    echo "Last migration rolled back successfully\n";
} else {
    echo "No migration to rollback\n";
}
```

#### Check Before Rollback

```php
// Get all migrations
$allMigrations = $migrationManager->getMigrations();
$executed = [];

foreach ($allMigrations as $version => $migration) {
    if ($migrationManager->isMigrationExecuted($migration)) {
        $executed[] = $version;
    }
}

if (!empty($executed)) {
    $lastExecuted = end($executed);
    echo "Last executed migration: $lastExecuted\n";
    
    // Confirm before rollback
    $confirm = readline("Do you want to rollback this migration? (y/n): ");
    if (strtolower($confirm) === 'y') {
        $migrationManager->rollback();
    }
}
```

## Migration Tracking Table

MulerTech Database automatically maintains a `migration_history` table:

```sql
CREATE TABLE `migration_history` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `version` varchar(13) NOT NULL,
    `executed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `execution_time` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Check History

```sql
-- View all executed migrations
SELECT version, executed_at, execution_time 
FROM migration_history 
ORDER BY executed_at DESC;

-- View last migration
SELECT version, executed_at 
FROM migration_history 
ORDER BY executed_at DESC 
LIMIT 1;
```

## Error Handling

### Failed Migration

```php
try {
    $migrationManager->migrate();
    echo "All migrations executed successfully\n";
} catch (RuntimeException $e) {
    echo "Error during execution: " . $e->getMessage() . "\n";
    
    // On error, transaction is automatically rolled back
    // Failed migration is not recorded in history
}
```

### Failed Rollback

```php
try {
    $success = $migrationManager->rollback();
    if (!$success) {
        echo "No migration to rollback\n";
    }
} catch (RuntimeException $e) {
    echo "Error during rollback: " . $e->getMessage() . "\n";
}
```

## Complete Workflow

```php
<?php
// Complete usage example

use MulerTech\Database\Schema\Migration\MigrationManager;

try {
    // 1. Initialize manager
    $migrationManager = new MigrationManager($entityManager);
    
    // 2. Load migrations
    $migrationManager->registerMigrations(__DIR__ . '/migrations');
    
    // 3. Check pending migrations
    $pending = $migrationManager->getPendingMigrations();
    if (empty($pending)) {
        echo "No pending migrations\n";
        exit(0);
    }
    
    echo "Pending migrations:\n";
    foreach ($pending as $migration) {
        echo "- " . $migration->getVersion() . "\n";
    }
    
    // 4. Ask for confirmation
    $confirm = readline("Execute these migrations? (y/n): ");
    if (strtolower($confirm) !== 'y') {
        echo "Operation cancelled\n";
        exit(0);
    }
    
    // 5. Execute migrations
    $executed = $migrationManager->migrate();
    echo "$executed migration(s) executed successfully\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Best Practices

### 1. Atomic Migrations

Each migration should represent a single logical change:

```php
// ✅ Good: Single responsibility
class Migration202412131200 extends Migration
{
    public function up(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $queryBuilder->raw("
            ALTER TABLE users 
            ADD COLUMN email_verified_at DATETIME NULL,
            ADD COLUMN email_verification_token VARCHAR(64) NULL
        ")->execute();
    }
    
    public function down(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $queryBuilder->raw("
            ALTER TABLE users 
            DROP COLUMN email_verified_at,
            DROP COLUMN email_verification_token
        ")->execute();
    }
}
```

### 2. Always Implement `down()`

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    $queryBuilder->raw("
        CREATE TABLE user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(128) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL
        )
    ")->execute();
}

public function down(): void
{
    $queryBuilder = $this->createQueryBuilder();
    $queryBuilder->raw("DROP TABLE user_sessions")->execute();
}
```

### 3. Data Validation

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    // Check that existing data is compatible
    $result = $queryBuilder->raw("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE email IS NULL OR email = ''
    ")->execute()->fetchScalar();
    
    if ($result > 0) {
        throw new RuntimeException(
            "Cannot add NOT NULL constraint: {$result} rows have invalid email"
        );
    }
    
    // Modify column
    $queryBuilder->raw("
        ALTER TABLE users 
        MODIFY COLUMN email VARCHAR(255) NOT NULL
    ")->execute();
}
```

### 4. Batch Operations for Large Volumes

```php
class Migration202412131200 extends Migration
{
    public function up(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        // Batch processing to avoid timeouts
        $batchSize = 1000;
        $offset = 0;
        
        do {
            $rows = $queryBuilder->raw("
                SELECT id, old_data 
                FROM large_table 
                LIMIT ? OFFSET ?
            ")->bind([$batchSize, $offset])->execute()->fetchAll();
            
            foreach ($rows as $row) {
                $newData = strtoupper($row['old_data']);
                
                $updateQuery = $this->createQueryBuilder();
                $updateQuery->raw("
                    UPDATE large_table 
                    SET new_data = ? 
                    WHERE id = ?
                ")->bind([$newData, $row['id']])->execute();
            }
            
            $offset += $batchSize;
        } while (count($rows) === $batchSize);
    }

    public function down(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $queryBuilder->raw("
            UPDATE large_table 
            SET new_data = NULL
        ")->execute();
    }
}
```

### 5. Test Before Deployment

```php
// In a test environment
$testMigrationManager = new MigrationManager($testEntityManager);
$testMigrationManager->registerMigrations('/path/to/migrations');

try {
    $testMigrationManager->migrate();
    echo "Migration tests successful\n";
} catch (Exception $e) {
    echo "Migration tests failed: " . $e->getMessage() . "\n";
    exit(1);
}
```

### 6. Backup Before Migration

```php
// Create backup before important migrations
$backup = "backup_" . date('Y-m-d_H-i-s') . ".sql";
exec("mysqldump -u user -p database > $backup");

try {
    $migrationManager->migrate();
    echo "Migrations successful, backup kept at $backup\n";
} catch (Exception $e) {
    echo "Migration failed, restore from $backup if necessary\n";
    throw $e;
}
```

### 7. Post-Migration Verification

```php
// After migration, verify state
$allMigrations = $migrationManager->getMigrations();
$pendingAfter = $migrationManager->getPendingMigrations();

if (empty($pendingAfter)) {
    echo "All migrations have been applied\n";
    echo "Total migrations: " . count($allMigrations) . "\n";
} else {
    echo "Warning: " . count($pendingAfter) . " migration(s) still pending\n";
}
```

---

**See also:**
- [Migration Tools](migration-tools.md) - CLI commands and schema comparison
