# Utilitaires de Test

Collection d'utilitaires et d'outils pour faciliter l'écriture et l'exécution des tests avec MulerTech Database.

## Table des Matières
- [Factories et builders](#factories-et-builders)
- [Assertions personnalisées](#assertions-personnalisées)
- [Helpers de base de données](#helpers-de-base-de-données)
- [Générateurs de données](#générateurs-de-données)
- [Outils de performance](#outils-de-performance)
- [Debugging et profiling](#debugging-et-profiling)

## Factories et builders

### Entity Factory avancée

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use ReflectionClass;
use DateTime;

class EntityFactory
{
    private static ?Generator $faker = null;
    private static array $sequences = [];
    private static array $defaults = [];

    public static function getFaker(): Generator
    {
        if (self::$faker === null) {
            self::$faker = FakerFactory::create('fr_FR');
        }
        return self::$faker;
    }

    public static function create(string $entityClass, array $attributes = []): object
    {
        $reflection = new ReflectionClass($entityClass);
        $entity = $reflection->newInstance();
        
        $defaults = self::getDefaults($entityClass);
        $data = array_merge($defaults, $attributes);
        
        foreach ($data as $property => $value) {
            $setter = 'set' . ucfirst($property);
            if (method_exists($entity, $setter)) {
                $entity->$setter($value);
            }
        }
        
        return $entity;
    }

    public static function createMany(string $entityClass, int $count, array $attributes = []): array
    {
        $entities = [];
        for ($i = 0; $i < $count; $i++) {
            $entities[] = self::create($entityClass, $attributes);
        }
        return $entities;
    }

    public static function sequence(string $key, callable $generator): callable
    {
        return function() use ($key, $generator) {
            if (!isset(self::$sequences[$key])) {
                self::$sequences[$key] = 0;
            }
            return $generator(self::$sequences[$key]++);
        };
    }

    public static function define(string $entityClass, array $defaults): void
    {
        self::$defaults[$entityClass] = $defaults;
    }

    private static function getDefaults(string $entityClass): array
    {
        if (isset(self::$defaults[$entityClass])) {
            return self::resolveCallables(self::$defaults[$entityClass]);
        }

        // Defaults automatiques basés sur le nom de classe
        return self::generateAutoDefaults($entityClass);
    }

    private static function resolveCallables(array $data): array
    {
        $resolved = [];
        foreach ($data as $key => $value) {
            $resolved[$key] = is_callable($value) ? $value() : $value;
        }
        return $resolved;
    }

    private static function generateAutoDefaults(string $entityClass): array
    {
        $faker = self::getFaker();
        $className = basename(str_replace('\\', '/', $entityClass));
        
        $defaults = [];
        
        switch ($className) {
            case 'User':
                $defaults = [
                    'name' => fn() => $faker->name,
                    'email' => fn() => $faker->unique()->email,
                    'password' => fn() => password_hash('password123', PASSWORD_DEFAULT),
                    'active' => fn() => $faker->boolean(80), // 80% actifs
                    'createdAt' => fn() => $faker->dateTimeBetween('-1 year'),
                    'updatedAt' => fn() => new DateTime(),
                ];
                break;
                
            case 'Post':
                $defaults = [
                    'title' => fn() => $faker->sentence(6, true),
                    'content' => fn() => $faker->paragraphs(3, true),
                    'published' => fn() => $faker->boolean(70),
                    'createdAt' => fn() => $faker->dateTimeBetween('-6 months'),
                ];
                break;
                
            case 'Category':
                $defaults = [
                    'name' => fn() => $faker->words(2, true),
                    'description' => fn() => $faker->sentence,
                ];
                break;
        }
        
        return self::resolveCallables($defaults);
    }
}
```

### Builder Pattern pour entités complexes

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

use App\Entity\User;
use App\Entity\Post;
use App\Entity\Category;
use DateTime;

class UserBuilder
{
    private array $attributes = [];

    public static function create(): self
    {
        return new self();
    }

    public function withName(string $name): self
    {
        $this->attributes['name'] = $name;
        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->attributes['email'] = $email;
        return $this;
    }

    public function active(): self
    {
        $this->attributes['active'] = true;
        return $this;
    }

    public function inactive(): self
    {
        $this->attributes['active'] = false;
        return $this;
    }

    public function admin(): self
    {
        $this->attributes['role'] = 'admin';
        return $this;
    }

    public function withPosts(int $count): self
    {
        $this->attributes['_posts_count'] = $count;
        return $this;
    }

    public function createdAt(DateTime $date): self
    {
        $this->attributes['createdAt'] = $date;
        return $this;
    }

    public function build(): User
    {
        $user = EntityFactory::create(User::class, $this->attributes);
        
        // Gérer les relations
        if (isset($this->attributes['_posts_count'])) {
            $postsCount = $this->attributes['_posts_count'];
            for ($i = 0; $i < $postsCount; $i++) {
                $post = EntityFactory::create(Post::class, ['user' => $user]);
                $user->addPost($post);
            }
        }
        
        return $user;
    }

    public function buildAndPersist($entityManager): User
    {
        $user = $this->build();
        $entityManager->persist($user);
        
        foreach ($user->getPosts() as $post) {
            $entityManager->persist($post);
        }
        
        $entityManager->flush();
        return $user;
    }
}

class PostBuilder
{
    private array $attributes = [];

    public static function create(): self
    {
        return new self();
    }

    public function withTitle(string $title): self
    {
        $this->attributes['title'] = $title;
        return $this;
    }

    public function withContent(string $content): self
    {
        $this->attributes['content'] = $content;
        return $this;
    }

    public function published(): self
    {
        $this->attributes['published'] = true;
        return $this;
    }

    public function draft(): self
    {
        $this->attributes['published'] = false;
        return $this;
    }

    public function byUser(User $user): self
    {
        $this->attributes['user'] = $user;
        return $this;
    }

    public function inCategory(Category $category): self
    {
        $this->attributes['category'] = $category;
        return $this;
    }

    public function withComments(int $count): self
    {
        $this->attributes['_comments_count'] = $count;
        return $this;
    }

    public function build(): Post
    {
        return EntityFactory::create(Post::class, $this->attributes);
    }
}
```

## Assertions personnalisées

### Trait d'assertions pour base de données

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\Assert;
use MulerTech\Database\EntityManager;

trait DatabaseAssertions
{
    protected function assertDatabaseHas(string $table, array $criteria, string $message = ''): void
    {
        $count = $this->getDatabaseCount($table, $criteria);
        Assert::assertGreaterThan(
            0, 
            $count, 
            $message ?: "Failed asserting that table '{$table}' contains record with criteria: " . json_encode($criteria)
        );
    }

    protected function assertDatabaseMissing(string $table, array $criteria, string $message = ''): void
    {
        $count = $this->getDatabaseCount($table, $criteria);
        Assert::assertEquals(
            0, 
            $count, 
            $message ?: "Failed asserting that table '{$table}' does not contain record with criteria: " . json_encode($criteria)
        );
    }

    protected function assertDatabaseCount(string $table, int $expectedCount, array $criteria = [], string $message = ''): void
    {
        $count = $this->getDatabaseCount($table, $criteria);
        Assert::assertEquals(
            $expectedCount, 
            $count, 
            $message ?: "Failed asserting that table '{$table}' contains exactly {$expectedCount} records"
        );
    }

    protected function assertEntityExists(string $entityClass, $id, string $message = ''): void
    {
        $entity = $this->getEntityManager()->find($entityClass, $id);
        Assert::assertNotNull(
            $entity, 
            $message ?: "Failed asserting that entity '{$entityClass}' with id '{$id}' exists"
        );
    }

    protected function assertEntityMissing(string $entityClass, $id, string $message = ''): void
    {
        $entity = $this->getEntityManager()->find($entityClass, $id);
        Assert::assertNull(
            $entity, 
            $message ?: "Failed asserting that entity '{$entityClass}' with id '{$id}' does not exist"
        );
    }

    protected function assertEntityHasProperty(object $entity, string $property, $expectedValue, string $message = ''): void
    {
        $getter = 'get' . ucfirst($property);
        if (!method_exists($entity, $getter)) {
            $getter = 'is' . ucfirst($property);
        }
        
        Assert::assertTrue(
            method_exists($entity, $getter), 
            "Entity does not have getter for property '{$property}'"
        );
        
        $actualValue = $entity->$getter();
        Assert::assertEquals(
            $expectedValue, 
            $actualValue, 
            $message ?: "Failed asserting that entity property '{$property}' equals expected value"
        );
    }

    protected function assertQueryResultCount(string $dql, int $expectedCount, array $parameters = [], string $message = ''): void
    {
        $query = $this->getEntityManager()->createQuery($dql);
        
        foreach ($parameters as $key => $value) {
            $query->setParameter($key, $value);
        }
        
        $result = $query->getResult();
        $count = count($result);
        
        Assert::assertEquals(
            $expectedCount, 
            $count, 
            $message ?: "Failed asserting that query returns exactly {$expectedCount} results. Got {$count}."
        );
    }

    private function getDatabaseCount(string $table, array $criteria): int
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = "SELECT COUNT(*) FROM `{$table}`";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "`{$field}` = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        return (int) $connection->fetchOne($sql, $params);
    }

    abstract protected function getEntityManager(): EntityManager;
}
```

### Assertions de performance

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\Assert;

trait PerformanceAssertions
{
    protected function assertExecutionTimeBelow(float $maxTime, callable $callback, string $message = ''): void
    {
        $startTime = microtime(true);
        $callback();
        $executionTime = microtime(true) - $startTime;
        
        Assert::assertLessThan(
            $maxTime, 
            $executionTime, 
            $message ?: "Execution time ({$executionTime}s) exceeded maximum allowed time ({$maxTime}s)"
        );
    }

    protected function assertMemoryUsageBelow(int $maxBytes, callable $callback, string $message = ''): void
    {
        $startMemory = memory_get_usage(true);
        $callback();
        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;
        
        Assert::assertLessThan(
            $maxBytes, 
            $memoryUsed, 
            $message ?: "Memory usage ({$memoryUsed} bytes) exceeded maximum allowed ({$maxBytes} bytes)"
        );
    }

    protected function assertQueryCountBelow(int $maxQueries, callable $callback, string $message = ''): void
    {
        $queryLogger = new QueryLogger();
        $this->getEntityManager()->getConnection()->getConfiguration()->setSQLLogger($queryLogger);
        
        $callback();
        
        $queryCount = $queryLogger->getQueryCount();
        
        Assert::assertLessThan(
            $maxQueries, 
            $queryCount, 
            $message ?: "Query count ({$queryCount}) exceeded maximum allowed ({$maxQueries})"
        );
    }
}

class QueryLogger
{
    private int $queryCount = 0;
    private array $queries = [];

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->queryCount++;
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'start_time' => microtime(true)
        ];
    }

    public function stopQuery(): void
    {
        if (!empty($this->queries)) {
            $lastIndex = count($this->queries) - 1;
            $this->queries[$lastIndex]['end_time'] = microtime(true);
            $this->queries[$lastIndex]['execution_time'] = 
                $this->queries[$lastIndex]['end_time'] - $this->queries[$lastIndex]['start_time'];
        }
    }

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getTotalExecutionTime(): float
    {
        return array_sum(array_column($this->queries, 'execution_time'));
    }
}
```

## Helpers de base de données

### Database Helper

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

use MulerTech\Database\EntityManager;
use MulerTech\Database\Connection\ConnectionInterface;

class DatabaseHelper
{
    private EntityManager $entityManager;
    private ConnectionInterface $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
    }

    public function truncateTable(string $tableName): void
    {
        $this->connection->execute("TRUNCATE TABLE `{$tableName}`");
    }

    public function truncateAllTables(): void
    {
        // Désactiver les contraintes temporairement
        $this->connection->execute('SET FOREIGN_KEY_CHECKS = 0');
        
        $tables = $this->getAllTables();
        foreach ($tables as $table) {
            $this->truncateTable($table);
        }
        
        // Réactiver les contraintes
        $this->connection->execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function resetAutoIncrement(string $tableName, int $value = 1): void
    {
        $this->connection->execute("ALTER TABLE `{$tableName}` AUTO_INCREMENT = {$value}");
    }

    public function getAllTables(): array
    {
        $result = $this->connection->query("SHOW TABLES")->fetchAll();
        return array_map('array_values', $result);
    }

    public function getTableRowCount(string $tableName): int
    {
        return (int) $this->connection->query("SELECT COUNT(*) FROM `{$tableName}`")->fetchColumn();
    }

    public function disableForeignKeyChecks(): void
    {
        $this->connection->execute('SET FOREIGN_KEY_CHECKS = 0');
    }

    public function enableForeignKeyChecks(): void
    {
        $this->connection->execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insertRawData(string $tableName, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data[0]);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        
        $sql = "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES ({$placeholders})";
        
        foreach ($data as $row) {
            $this->connection->execute($sql, array_values($row));
        }
    }

    public function backupTable(string $tableName): string
    {
        $backupName = $tableName . '_backup_' . time();
        $this->connection->execute("CREATE TABLE `{$backupName}` AS SELECT * FROM `{$tableName}`");
        return $backupName;
    }

    public function restoreTable(string $tableName, string $backupName): void
    {
        $this->truncateTable($tableName);
        $this->connection->execute("INSERT INTO `{$tableName}` SELECT * FROM `{$backupName}`");
    }

    public function executeRawSql(string $sql, array $params = []): void
    {
        $this->connection->execute($sql, $params);
    }

    public function queryRawSql(string $sql, array $params = []): array
    {
        return $this->connection->query($sql, $params)->fetchAll();
    }
}
```

### Transaction Helper

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

use MulerTech\Database\EntityManager;
use Exception;

class TransactionHelper
{
    private EntityManager $entityManager;
    private array $transactionStack = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function inTransaction(callable $callback)
    {
        $connection = $this->entityManager->getConnection();
        
        $connection->beginTransaction();
        $this->transactionStack[] = microtime(true);
        
        try {
            $result = $callback();
            $connection->commit();
            array_pop($this->transactionStack);
            return $result;
        } catch (Exception $e) {
            $connection->rollback();
            array_pop($this->transactionStack);
            throw $e;
        }
    }

    public function inRollbackTransaction(callable $callback)
    {
        $connection = $this->entityManager->getConnection();
        
        $connection->beginTransaction();
        
        try {
            $result = $callback();
            // Toujours faire un rollback
            $connection->rollback();
            return $result;
        } catch (Exception $e) {
            $connection->rollback();
            throw $e;
        }
    }

    public function withSavepoint(string $name, callable $callback)
    {
        $connection = $this->entityManager->getConnection();
        
        if (!$connection->isTransactionActive()) {
            $connection->beginTransaction();
            $shouldCommit = true;
        } else {
            $shouldCommit = false;
        }
        
        $connection->createSavepoint($name);
        
        try {
            $result = $callback();
            $connection->releaseSavepoint($name);
            
            if ($shouldCommit) {
                $connection->commit();
            }
            
            return $result;
        } catch (Exception $e) {
            $connection->rollbackToSavepoint($name);
            
            if ($shouldCommit) {
                $connection->rollback();
            }
            
            throw $e;
        }
    }

    public function isInTransaction(): bool
    {
        return $this->entityManager->getConnection()->isTransactionActive();
    }

    public function getTransactionDepth(): int
    {
        return count($this->transactionStack);
    }
}
```

## Générateurs de données

### Générateur de données réalistes

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

use Faker\Generator;
use DateTime;

class DataGenerator
{
    private Generator $faker;

    public function __construct(Generator $faker)
    {
        $this->faker = $faker;
    }

    public function generateUsers(int $count): array
    {
        $users = [];
        
        for ($i = 0; $i < $count; $i++) {
            $users[] = [
                'name' => $this->faker->name,
                'email' => $this->faker->unique()->email,
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'active' => $this->faker->boolean(85), // 85% actifs
                'role' => $this->faker->randomElement(['user', 'moderator', 'admin']),
                'created_at' => $this->faker->dateTimeBetween('-2 years')->format('Y-m-d H:i:s'),
                'updated_at' => $this->faker->dateTimeBetween('-1 month')->format('Y-m-d H:i:s'),
            ];
        }
        
        return $users;
    }

    public function generatePosts(array $userIds, array $categoryIds, int $count): array
    {
        $posts = [];
        
        for ($i = 0; $i < $count; $i++) {
            $createdAt = $this->faker->dateTimeBetween('-1 year');
            $updatedAt = $this->faker->dateTimeBetween($createdAt);
            
            $posts[] = [
                'title' => $this->faker->sentence(6, true),
                'slug' => $this->faker->slug,
                'content' => $this->faker->paragraphs(3, true),
                'excerpt' => $this->faker->text(200),
                'published' => $this->faker->boolean(75), // 75% publiés
                'featured' => $this->faker->boolean(20), // 20% mis en avant
                'view_count' => $this->faker->numberBetween(0, 10000),
                'user_id' => $this->faker->randomElement($userIds),
                'category_id' => $this->faker->randomElement($categoryIds),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
            ];
        }
        
        return $posts;
    }

    public function generateComments(array $userIds, array $postIds, int $count): array
    {
        $comments = [];
        
        for ($i = 0; $i < $count; $i++) {
            $comments[] = [
                'content' => $this->faker->paragraphs(2, true),
                'approved' => $this->faker->boolean(90), // 90% approuvés
                'user_id' => $this->faker->randomElement($userIds),
                'post_id' => $this->faker->randomElement($postIds),
                'parent_id' => $this->faker->optional(0.3)->randomElement($comments)['id'] ?? null,
                'created_at' => $this->faker->dateTimeBetween('-6 months')->format('Y-m-d H:i:s'),
            ];
        }
        
        return $comments;
    }

    public function generateCategories(): array
    {
        $categories = [
            ['name' => 'Technology', 'description' => 'Latest tech trends and innovations'],
            ['name' => 'Science', 'description' => 'Scientific discoveries and research'],
            ['name' => 'Sports', 'description' => 'Sports news and analysis'],
            ['name' => 'Travel', 'description' => 'Travel guides and experiences'],
            ['name' => 'Food', 'description' => 'Recipes and culinary experiences'],
            ['name' => 'Health', 'description' => 'Health tips and medical news'],
            ['name' => 'Business', 'description' => 'Business news and entrepreneurship'],
            ['name' => 'Entertainment', 'description' => 'Movies, music, and entertainment'],
        ];

        foreach ($categories as &$category) {
            $category['slug'] = strtolower(str_replace(' ', '-', $category['name']));
            $category['created_at'] = $this->faker->dateTimeBetween('-1 year')->format('Y-m-d H:i:s');
        }

        return $categories;
    }

    public function generateLargeDataset(string $type, int $count): \Generator
    {
        for ($i = 0; $i < $count; $i++) {
            yield match ($type) {
                'user' => $this->generateUsers(1)[0],
                'post' => $this->generatePosts([1], [1], 1)[0],
                'comment' => $this->generateComments([1], [1], 1)[0],
                default => throw new \InvalidArgumentException("Unknown type: {$type}")
            };
        }
    }
}
```

## Outils de performance

### Profiler de tests

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;

class TestProfiler implements TestListener
{
    private array $testTimes = [];
    private array $memoryUsage = [];
    private float $currentTestStartTime;
    private int $currentTestStartMemory;

    public function startTest(Test $test): void
    {
        $this->currentTestStartTime = microtime(true);
        $this->currentTestStartMemory = memory_get_usage(true);
    }

    public function endTest(Test $test, float $time): void
    {
        $testName = $test->getName();
        $className = get_class($test);
        $fullName = "{$className}::{$testName}";
        
        $this->testTimes[$fullName] = $time;
        $this->memoryUsage[$fullName] = memory_get_usage(true) - $this->currentTestStartMemory;
    }

    public function endTestSuite(TestSuite $suite): void
    {
        $this->generateReport();
    }

    private function generateReport(): void
    {
        if (empty($this->testTimes)) {
            return;
        }

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TEST PERFORMANCE REPORT\n";
        echo str_repeat("=", 80) . "\n";

        // Top 10 des tests les plus lents
        arsort($this->testTimes);
        $slowestTests = array_slice($this->testTimes, 0, 10, true);

        echo "\nSlowest Tests:\n";
        echo str_repeat("-", 40) . "\n";
        foreach ($slowestTests as $test => $time) {
            $memory = $this->formatBytes($this->memoryUsage[$test]);
            echo sprintf("%.4fs %s %s\n", $time, $memory, $test);
        }

        // Top 10 des tests consommant le plus de mémoire
        arsort($this->memoryUsage);
        $memoryHungryTests = array_slice($this->memoryUsage, 0, 10, true);

        echo "\nMemory Hungry Tests:\n";
        echo str_repeat("-", 40) . "\n";
        foreach ($memoryHungryTests as $test => $memory) {
            $time = $this->testTimes[$test];
            echo sprintf("%s %.4fs %s\n", $this->formatBytes($memory), $time, $test);
        }

        // Statistiques globales
        $totalTime = array_sum($this->testTimes);
        $totalMemory = array_sum($this->memoryUsage);
        $testCount = count($this->testTimes);

        echo "\nOverall Statistics:\n";
        echo str_repeat("-", 40) . "\n";
        echo sprintf("Total tests: %d\n", $testCount);
        echo sprintf("Total time: %.4fs\n", $totalTime);
        echo sprintf("Average time: %.4fs\n", $totalTime / $testCount);
        echo sprintf("Total memory: %s\n", $this->formatBytes($totalMemory));
        echo sprintf("Average memory: %s\n", $this->formatBytes($totalMemory / $testCount));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        
        while ($bytes >= 1024 && $unit < 3) {
            $bytes /= 1024;
            $unit++;
        }
        
        return sprintf("%.2f%s", $bytes, $units[$unit]);
    }
}
```

### Benchmark Helper

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

class BenchmarkHelper
{
    public static function benchmark(callable $callback, int $iterations = 1): array
    {
        $times = [];
        $memoryUsages = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $callback();
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $times[] = $endTime - $startTime;
            $memoryUsages[] = $endMemory - $startMemory;
        }
        
        return [
            'iterations' => $iterations,
            'times' => $times,
            'memory_usages' => $memoryUsages,
            'avg_time' => array_sum($times) / $iterations,
            'min_time' => min($times),
            'max_time' => max($times),
            'avg_memory' => array_sum($memoryUsages) / $iterations,
            'min_memory' => min($memoryUsages),
            'max_memory' => max($memoryUsages),
        ];
    }

    public static function compareBenchmarks(array $benchmarks): array
    {
        $comparison = [];
        $baselineName = array_key_first($benchmarks);
        $baseline = $benchmarks[$baselineName];
        
        foreach ($benchmarks as $name => $benchmark) {
            $timeRatio = $benchmark['avg_time'] / $baseline['avg_time'];
            $memoryRatio = $benchmark['avg_memory'] / $baseline['avg_memory'];
            
            $comparison[$name] = [
                'time_ratio' => $timeRatio,
                'memory_ratio' => $memoryRatio,
                'time_change' => ($timeRatio - 1) * 100,
                'memory_change' => ($memoryRatio - 1) * 100,
                'faster' => $timeRatio < 1,
                'more_efficient' => $memoryRatio < 1,
            ];
        }
        
        return $comparison;
    }
}
```

## Debugging et profiling

### Debug Helper

```php
<?php

declare(strict_types=1);

namespace Tests\Utilities;

class DebugHelper
{
    private static bool $enabled = false;
    private static array $logs = [];

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function log(string $message, array $context = []): void
    {
        if (!self::$enabled) {
            return;
        }

        self::$logs[] = [
            'timestamp' => microtime(true),
            'message' => $message,
            'context' => $context,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
        ];
    }

    public static function dump($variable, string $label = ''): void
    {
        if (!self::$enabled) {
            return;
        }

        $output = $label ? "{$label}: " : '';
        $output .= print_r($variable, true);
        
        self::log($output);
    }

    public static function getLogs(): array
    {
        return self::$logs;
    }

    public static function clearLogs(): void
    {
        self::$logs = [];
    }

    public static function printLogs(): void
    {
        foreach (self::$logs as $log) {
            $time = date('H:i:s', (int)$log['timestamp']);
            $microseconds = sprintf('%06d', ($log['timestamp'] - floor($log['timestamp'])) * 1000000);
            
            echo "[{$time}.{$microseconds}] {$log['message']}\n";
            
            if (!empty($log['context'])) {
                echo "Context: " . json_encode($log['context'], JSON_PRETTY_PRINT) . "\n";
            }
        }
    }

    public static function profileMethod(object $object, string $method, array $args = []): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $result = call_user_func_array([$object, $method], $args);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        return [
            'result' => $result,
            'execution_time' => $endTime - $startTime,
            'memory_used' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }
}
```

---

**Voir aussi :**
- [Tests unitaires](unit-tests.md)
- [Tests d'intégration](integration-tests.md)
- [Configuration Docker](docker-setup.md)
