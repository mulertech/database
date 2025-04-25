<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MulerTech\Database\Migration\MigrationGenerator;
use MulerTech\Database\Migration\MigrationManager;
use MulerTech\Database\Migration\Schema\SchemaComparer;
use MulerTech\Database\Relational\Sql\InformationSchema;

// Initialize your EntityManager
$entityManager = new YourEntityManager(); // Replace with your actual implementation

// Initialize migration components
$informationSchema = new InformationSchema();
$dbMapping = $entityManager->getDbMapping();
$dbName = \MulerTech\Database\PhpInterface\PhpDatabaseManager::populateParameters()['dbname'];

// Create schema comparer
$schemaComparer = new SchemaComparer(
    $informationSchema,
    $dbMapping,
    $dbName
);

// Create migration generator
$migrationsDir = __DIR__ . '/../src/Migrations';
$migrationGenerator = new MigrationGenerator($schemaComparer, $migrationsDir);

// Generate a migration if needed
$migrationFile = $migrationGenerator->generateMigration('Update schema');

if ($migrationFile) {
    echo "Generated migration file: {$migrationFile}\n";
} else {
    echo "No schema changes detected.\n";
}

// Create migration manager
$migrationManager = new MigrationManager($entityManager);

// Register migrations (you might want to do this automatically by scanning the migrations directory)
$migrations = [
    // Add your migration instances here
];
$migrationManager->registerMigrations($migrations);

// Execute pending migrations
$executed = $migrationManager->migrate();
echo "Executed {$executed} migrations.\n";
