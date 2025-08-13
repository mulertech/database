# Tests d'Intégration

Les tests d'intégration vérifient que les différents composants de MulerTech Database fonctionnent correctement ensemble.

## Table des Matières
- [Configuration des tests d'intégration](#configuration-des-tests-dintégration)
- [Tests de bout en bout](#tests-de-bout-en-bout)
- [Tests des migrations](#tests-des-migrations)
- [Tests de performance intégrée](#tests-de-performance-intégrée)
- [Tests avec données réelles](#tests-avec-données-réelles)
- [Validation de l'écosystème](#validation-de-lécosystème)

## Configuration des tests d'intégration

### Base de données de test séparée

```php
<?php
// tests/Integration/bootstrap.php

declare(strict_types=1);

use MulerTech\Database\EntityManager;
use MulerTech\Database\Configuration\Configuration;
use MulerTech\Database\Schema\SchemaManager;

class IntegrationTestBootstrap
{
    private static ?EntityManager $entityManager = null;
    private static bool $schemaCreated = false;

    public static function getEntityManager(): EntityManager
    {
        if (self::$entityManager === null) {
            $config = new Configuration([
                'driver' => 'mysql',
                'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'port' => (int)($_ENV['DB_PORT'] ?? 3306),
                'database' => $_ENV['DB_DATABASE'] ?? 'mulertech_integration_test',
                'username' => $_ENV['DB_USERNAME'] ?? 'test',
                'password' => $_ENV['DB_PASSWORD'] ?? 'test',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);

            self::$entityManager = new EntityManager($config);
            self::setupSchema();
        }

        return self::$entityManager;
    }

    private static function setupSchema(): void
    {
        if (!self::$schemaCreated) {
            $schemaManager = self::$entityManager->getSchemaManager();
            
            // Supprimer et recréer le schéma
            $schemaManager->dropDatabase();
            $schemaManager->createDatabase();
            $schemaManager->createSchema();
            
            self::$schemaCreated = true;
        }
    }

    public static function tearDown(): void
    {
        if (self::$entityManager !== null) {
            $schemaManager = self::$entityManager->getSchemaManager();
            $schemaManager->dropDatabase();
            
            self::$entityManager = null;
            self::$schemaCreated = false;
        }
    }
}
```

### Classe de base pour tests d'intégration

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use MulerTech\Database\EntityManager;
use MulerTech\Database\Connection\ConnectionInterface;

abstract class IntegrationTestCase extends TestCase
{
    protected EntityManager $entityManager;
    protected ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = IntegrationTestBootstrap::getEntityManager();
        $this->connection = $this->entityManager->getConnection();
        
        // Nettoyer les données avant chaque test
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    protected function cleanDatabase(): void
    {
        // Désactiver les contraintes de clés étrangères temporairement
        $this->connection->execute('SET FOREIGN_KEY_CHECKS = 0');
        
        // Obtenir toutes les tables
        $tables = $this->connection->query("SHOW TABLES")->fetchAll();
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $this->connection->execute("TRUNCATE TABLE `{$tableName}`");
        }
        
        // Réactiver les contraintes
        $this->connection->execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function loadFixtures(string $fixtureClass): void
    {
        $fixture = new $fixtureClass($this->entityManager);
        $fixture->load();
    }

    protected function assertTableExists(string $tableName): void
    {
        $result = $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
            [$tableName]
        )->fetchColumn();

        $this->assertEquals(1, $result, "Table '{$tableName}' should exist");
    }

    protected function assertColumnExists(string $tableName, string $columnName): void
    {
        $result = $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            [$tableName, $columnName]
        )->fetchColumn();

        $this->assertEquals(1, $result, "Column '{$columnName}' should exist in table '{$tableName}'");
    }

    protected function getTableRowCount(string $tableName): int
    {
        return (int)$this->connection->query("SELECT COUNT(*) FROM `{$tableName}`")->fetchColumn();
    }
}
```

## Tests de bout en bout

### Test du cycle de vie complet d'une entité

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\User;
use App\Entity\Post;
use App\Entity\Category;
use DateTime;

final class EntityLifecycleTest extends IntegrationTestCase
{
    public function testCompleteEntityLifecycle(): void
    {
        // 1. Création et persistance
        $user = new User();
        $user->setName('Integration Test User');
        $user->setEmail('integration@example.com');
        $user->setPassword('password123');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $userId = $user->getId();
        $this->assertNotNull($userId);

        // 2. Vérification en base
        $this->assertEquals(1, $this->getTableRowCount('users'));
        
        // 3. Récupération et modification
        $this->entityManager->clear();
        $retrievedUser = $this->entityManager->find(User::class, $userId);
        
        $this->assertNotNull($retrievedUser);
        $this->assertEquals('Integration Test User', $retrievedUser->getName());

        $retrievedUser->setName('Updated Name');
        $this->entityManager->flush();

        // 4. Vérification de la mise à jour
        $this->entityManager->clear();
        $updatedUser = $this->entityManager->find(User::class, $userId);
        $this->assertEquals('Updated Name', $updatedUser->getName());

        // 5. Suppression
        $this->entityManager->remove($updatedUser);
        $this->entityManager->flush();

        // 6. Vérification de la suppression
        $this->assertEquals(0, $this->getTableRowCount('users'));
        $deletedUser = $this->entityManager->find(User::class, $userId);
        $this->assertNull($deletedUser);
    }

    public function testComplexRelationships(): void
    {
        // Créer une catégorie
        $category = new Category();
        $category->setName('Technology');
        $category->setDescription('Tech-related posts');

        $this->entityManager->persist($category);

        // Créer un utilisateur
        $user = new User();
        $user->setName('Author');
        $user->setEmail('author@example.com');
        $user->setPassword('password');

        $this->entityManager->persist($user);

        // Créer plusieurs posts
        $posts = [];
        for ($i = 1; $i <= 3; $i++) {
            $post = new Post();
            $post->setTitle("Post {$i}");
            $post->setContent("Content for post {$i}");
            $post->setUser($user);
            $post->setCategory($category);
            $post->setPublished(true);

            $this->entityManager->persist($post);
            $posts[] = $post;
        }

        $this->entityManager->flush();

        // Vérifications
        $this->assertEquals(1, $this->getTableRowCount('users'));
        $this->assertEquals(1, $this->getTableRowCount('categories'));
        $this->assertEquals(3, $this->getTableRowCount('posts'));

        // Test des relations
        $this->entityManager->clear();
        
        $reloadedUser = $this->entityManager->find(User::class, $user->getId());
        $this->assertCount(3, $reloadedUser->getPosts());

        $reloadedCategory = $this->entityManager->find(Category::class, $category->getId());
        $this->assertCount(3, $reloadedCategory->getPosts());

        // Test de suppression en cascade
        $this->entityManager->remove($reloadedUser);
        $this->entityManager->flush();

        $this->assertEquals(0, $this->getTableRowCount('users'));
        $this->assertEquals(0, $this->getTableRowCount('posts')); // Si cascade configuré
        $this->assertEquals(1, $this->getTableRowCount('categories')); // Catégorie préservée
    }
}
```

### Test des transactions complexes

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\User;
use App\Entity\Post;
use Exception;

final class TransactionTest extends IntegrationTestCase
{
    public function testSuccessfulTransaction(): void
    {
        $this->connection->beginTransaction();

        try {
            // Créer plusieurs entités dans la transaction
            $user = new User();
            $user->setName('Transaction User');
            $user->setEmail('transaction@example.com');
            $user->setPassword('password');

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $post = new Post();
            $post->setTitle('Transaction Post');
            $post->setContent('Content created in transaction');
            $post->setUser($user);

            $this->entityManager->persist($post);
            $this->entityManager->flush();

            $this->connection->commit();

            // Vérifier que les données sont persistées
            $this->assertEquals(1, $this->getTableRowCount('users'));
            $this->assertEquals(1, $this->getTableRowCount('posts'));

        } catch (Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function testRollbackTransaction(): void
    {
        $this->connection->beginTransaction();

        try {
            // Créer des entités
            $user = new User();
            $user->setName('Rollback User');
            $user->setEmail('rollback@example.com');
            $user->setPassword('password');

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Vérifier que les données sont en attente
            $this->assertEquals(1, $this->getTableRowCount('users'));

            // Forcer une erreur pour déclencher le rollback
            throw new Exception('Simulated error');

        } catch (Exception $e) {
            $this->connection->rollback();
        }

        // Vérifier que les données ont été annulées
        $this->assertEquals(0, $this->getTableRowCount('users'));
    }

    public function testNestedTransactions(): void
    {
        $this->connection->beginTransaction();

        try {
            $user = new User();
            $user->setName('Outer Transaction User');
            $user->setEmail('outer@example.com');
            $user->setPassword('password');

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Savepoint pour transaction imbriquée
            $this->connection->createSavepoint('nested');

            try {
                $post = new Post();
                $post->setTitle('Nested Transaction Post');
                $post->setContent('This should be rolled back');
                $post->setUser($user);

                $this->entityManager->persist($post);
                $this->entityManager->flush();

                // Simuler une erreur dans la transaction imbriquée
                throw new Exception('Nested transaction error');

            } catch (Exception $e) {
                $this->connection->rollbackToSavepoint('nested');
            }

            $this->connection->commit();

            // L'utilisateur doit exister, mais pas le post
            $this->assertEquals(1, $this->getTableRowCount('users'));
            $this->assertEquals(0, $this->getTableRowCount('posts'));

        } catch (Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }
}
```

## Tests des migrations

### Test d'exécution de migrations

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use MulerTech\Database\Schema\MigrationManager;
use MulerTech\Database\Schema\Migration;
use MulerTech\Database\Schema\Schema;

final class MigrationIntegrationTest extends IntegrationTestCase
{
    private MigrationManager $migrationManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrationManager = new MigrationManager($this->connection);
    }

    public function testMigrationExecution(): void
    {
        // Créer une migration de test
        $migration = new class extends Migration {
            public function up(Schema $schema): void
            {
                $table = $schema->createTable('test_migration_table');
                $table->addColumn('id', 'integer', ['autoincrement' => true]);
                $table->addColumn('name', 'string', ['length' => 100]);
                $table->addColumn('created_at', 'datetime');
                $table->setPrimaryKey(['id']);
            }

            public function down(Schema $schema): void
            {
                $schema->dropTable('test_migration_table');
            }

            public function getDescription(): string
            {
                return 'Test migration for integration tests';
            }
        };

        // Exécuter la migration
        $this->migrationManager->executeMigration($migration, 'up');

        // Vérifier que la table a été créée
        $this->assertTableExists('test_migration_table');
        $this->assertColumnExists('test_migration_table', 'id');
        $this->assertColumnExists('test_migration_table', 'name');
        $this->assertColumnExists('test_migration_table', 'created_at');

        // Tester le rollback
        $this->migrationManager->executeMigration($migration, 'down');

        // Vérifier que la table a été supprimée
        $result = $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'test_migration_table'"
        )->fetchColumn();

        $this->assertEquals(0, $result);
    }

    public function testMigrationWithData(): void
    {
        // Migration qui ajoute une colonne et migre les données
        $migration = new class extends Migration {
            public function up(Schema $schema): void
            {
                // Créer la table initiale
                $table = $schema->createTable('users_migration_test');
                $table->addColumn('id', 'integer', ['autoincrement' => true]);
                $table->addColumn('full_name', 'string', ['length' => 200]);
                $table->setPrimaryKey(['id']);

                // Insérer des données de test
                $schema->getConnection()->executeStatement(
                    "INSERT INTO users_migration_test (full_name) VALUES (?), (?)",
                    ['John Doe', 'Jane Smith']
                );

                // Ajouter les nouvelles colonnes
                $table->addColumn('first_name', 'string', ['length' => 100, 'notnull' => false]);
                $table->addColumn('last_name', 'string', ['length' => 100, 'notnull' => false]);

                // Migrer les données
                $users = $schema->getConnection()->fetchAllAssociative("SELECT id, full_name FROM users_migration_test");
                foreach ($users as $user) {
                    $nameParts = explode(' ', $user['full_name'], 2);
                    $firstName = $nameParts[0];
                    $lastName = $nameParts[1] ?? '';

                    $schema->getConnection()->executeStatement(
                        "UPDATE users_migration_test SET first_name = ?, last_name = ? WHERE id = ?",
                        [$firstName, $lastName, $user['id']]
                    );
                }
            }

            public function down(Schema $schema): void
            {
                $schema->dropTable('users_migration_test');
            }
        };

        $this->migrationManager->executeMigration($migration, 'up');

        // Vérifier que les données ont été migrées correctement
        $users = $this->connection->fetchAllAssociative("SELECT * FROM users_migration_test ORDER BY id");

        $this->assertCount(2, $users);
        $this->assertEquals('John', $users[0]['first_name']);
        $this->assertEquals('Doe', $users[0]['last_name']);
        $this->assertEquals('Jane', $users[1]['first_name']);
        $this->assertEquals('Smith', $users[1]['last_name']);
    }
}
```

## Tests de performance intégrée

### Test de charge

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Performance;

use Tests\Integration\IntegrationTestCase;
use App\Entity\User;
use App\Entity\Post;

final class LoadTest extends IntegrationTestCase
{
    /**
     * @group performance
     */
    public function testHighVolumeInserts(): void
    {
        $startTime = microtime(true);
        $batchSize = 100;
        $totalRecords = 1000;

        for ($i = 0; $i < $totalRecords; $i++) {
            $user = new User();
            $user->setName("Load Test User {$i}");
            $user->setEmail("loadtest{$i}@example.com");
            $user->setPassword('password');

            $this->entityManager->persist($user);

            // Flush par batch pour optimiser
            if (($i + 1) % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;
        $recordsPerSecond = $totalRecords / $executionTime;

        // Assertions de performance
        $this->assertLessThan(10.0, $executionTime, 'Should insert 1000 records in less than 10 seconds');
        $this->assertGreaterThan(100, $recordsPerSecond, 'Should process at least 100 records per second');
        
        // Vérifier que tous les records ont été insérés
        $this->assertEquals($totalRecords, $this->getTableRowCount('users'));
    }

    /**
     * @group performance
     */
    public function testConcurrentReadsAndWrites(): void
    {
        // Préparer des données initiales
        for ($i = 0; $i < 100; $i++) {
            $user = new User();
            $user->setName("Concurrent User {$i}");
            $user->setEmail("concurrent{$i}@example.com");
            $user->setPassword('password');
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $startTime = microtime(true);

        // Simuler des opérations concurrentes
        $readOperations = 0;
        $writeOperations = 0;

        for ($i = 0; $i < 200; $i++) {
            if ($i % 2 === 0) {
                // Opération de lecture
                $randomId = mt_rand(1, 100);
                $user = $this->entityManager->find(User::class, $randomId);
                if ($user) {
                    $readOperations++;
                }
            } else {
                // Opération d'écriture
                $user = new User();
                $user->setName("New User {$i}");
                $user->setEmail("new{$i}@example.com");
                $user->setPassword('password');
                $this->entityManager->persist($user);
                $writeOperations++;
            }

            if ($i % 20 === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
        $endTime = microtime(true);

        $totalTime = $endTime - $startTime;
        $operationsPerSecond = 200 / $totalTime;

        $this->assertGreaterThan(50, $operationsPerSecond, 'Should handle at least 50 operations per second');
        $this->assertGreaterThan(95, $readOperations, 'Should complete most read operations');
        $this->assertGreaterThan(95, $writeOperations, 'Should complete most write operations');
    }
}
```

## Tests avec données réelles

### Test avec jeu de données réaliste

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\RealWorld;

use Tests\Integration\IntegrationTestCase;
use App\Entity\User;
use App\Entity\Post;
use App\Entity\Category;
use App\Entity\Comment;

final class RealWorldDataTest extends IntegrationTestCase
{
    public function testBlogApplicationScenario(): void
    {
        // Créer des catégories réalistes
        $categories = $this->createCategories();
        
        // Créer des utilisateurs avec des profils variés
        $users = $this->createUsers();
        
        // Créer du contenu réaliste
        $posts = $this->createPosts($users, $categories);
        
        // Ajouter des interactions
        $this->createComments($users, $posts);
        
        // Tester des requêtes business réalistes
        $this->testBusinessQueries($categories, $users);
    }

    private function createCategories(): array
    {
        $categoryData = [
            ['Technology', 'Latest tech trends and tutorials'],
            ['Sports', 'Sports news and analysis'],
            ['Travel', 'Travel guides and experiences'],
            ['Food', 'Recipes and restaurant reviews'],
            ['Lifestyle', 'Health, fashion, and lifestyle tips'],
        ];

        $categories = [];
        foreach ($categoryData as [$name, $description]) {
            $category = new Category();
            $category->setName($name);
            $category->setDescription($description);
            $this->entityManager->persist($category);
            $categories[] = $category;
        }

        $this->entityManager->flush();
        return $categories;
    }

    private function createUsers(): array
    {
        $userData = [
            ['John Doe', 'john.doe@example.com'],
            ['Jane Smith', 'jane.smith@example.com'],
            ['Bob Johnson', 'bob.johnson@example.com'],
            ['Alice Brown', 'alice.brown@example.com'],
            ['Charlie Wilson', 'charlie.wilson@example.com'],
        ];

        $users = [];
        foreach ($userData as [$name, $email]) {
            $user = new User();
            $user->setName($name);
            $user->setEmail($email);
            $user->setPassword(password_hash('password123', PASSWORD_DEFAULT));
            $user->setActive(true);
            $this->entityManager->persist($user);
            $users[] = $user;
        }

        $this->entityManager->flush();
        return $users;
    }

    private function createPosts(array $users, array $categories): array
    {
        $postData = [
            'Getting Started with PHP 8.2',
            'The Future of Web Development',
            'Best Travel Destinations 2024',
            'Healthy Eating on a Budget',
            'Olympic Games Highlights',
            'Remote Work Best Practices',
            'Photography Tips for Beginners',
            'Sustainable Living Guide',
            'Machine Learning Basics',
            'Cooking Italian Cuisine',
        ];

        $posts = [];
        foreach ($postData as $index => $title) {
            $post = new Post();
            $post->setTitle($title);
            $post->setContent($this->generateRealisticContent($title));
            $post->setUser($users[$index % count($users)]);
            $post->setCategory($categories[$index % count($categories)]);
            $post->setPublished(mt_rand(0, 100) > 10); // 90% published
            $post->setCreatedAt(new \DateTime('-' . mt_rand(1, 365) . ' days'));
            
            $this->entityManager->persist($post);
            $posts[] = $post;
        }

        $this->entityManager->flush();
        return $posts;
    }

    private function createComments(array $users, array $posts): void
    {
        foreach ($posts as $post) {
            $commentCount = mt_rand(0, 5);
            
            for ($i = 0; $i < $commentCount; $i++) {
                $comment = new Comment();
                $comment->setContent($this->generateRealisticComment());
                $comment->setUser($users[mt_rand(0, count($users) - 1)]);
                $comment->setPost($post);
                $comment->setCreatedAt(new \DateTime('-' . mt_rand(1, 100) . ' days'));
                
                $this->entityManager->persist($comment);
            }
        }

        $this->entityManager->flush();
    }

    private function testBusinessQueries(array $categories, array $users): void
    {
        // Test : Articles les plus populaires
        $qb = $this->entityManager->createQueryBuilder();
        $popularPosts = $qb->select('p', 'COUNT(c.id) as comment_count')
                          ->from(Post::class, 'p')
                          ->leftJoin('p.comments', 'c')
                          ->where('p.published = :published')
                          ->groupBy('p.id')
                          ->orderBy('comment_count', 'DESC')
                          ->setMaxResults(5)
                          ->setParameter('published', true)
                          ->getQuery()
                          ->getResult();

        $this->assertNotEmpty($popularPosts);

        // Test : Utilisateurs actifs (avec posts récents)
        $qb = $this->entityManager->createQueryBuilder();
        $activeUsers = $qb->select('u')
                         ->from(User::class, 'u')
                         ->join('u.posts', 'p')
                         ->where('p.createdAt > :date')
                         ->andWhere('u.active = :active')
                         ->setParameter('date', new \DateTime('-30 days'))
                         ->setParameter('active', true)
                         ->getQuery()
                         ->getResult();

        $this->assertNotEmpty($activeUsers);

        // Test : Statistiques par catégorie
        $qb = $this->entityManager->createQueryBuilder();
        $categoryStats = $qb->select('c.name', 'COUNT(p.id) as post_count')
                           ->from(Category::class, 'c')
                           ->leftJoin('c.posts', 'p')
                           ->groupBy('c.id')
                           ->getQuery()
                           ->getResult();

        $this->assertCount(count($categories), $categoryStats);
    }

    private function generateRealisticContent(string $title): string
    {
        return "This is a comprehensive article about {$title}. " .
               "It covers various aspects and provides valuable insights for readers. " .
               "The content is well-researched and includes practical examples. " .
               "Whether you're a beginner or an expert, you'll find useful information here.";
    }

    private function generateRealisticComment(): string
    {
        $comments = [
            "Great article! Thanks for sharing.",
            "Very informative, learned a lot.",
            "I have a different opinion on this topic.",
            "Could you provide more examples?",
            "Excellent work, keep it up!",
            "This helped me solve my problem.",
            "Looking forward to more content like this.",
        ];

        return $comments[array_rand($comments)];
    }
}
```

## Validation de l'écosystème

### Test de compatibilité avec d'autres composants

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Ecosystem;

use Tests\Integration\IntegrationTestCase;
use MulerTech\Database\Cache\CacheManager;
use MulerTech\Database\Event\EventDispatcher;
use MulerTech\Database\Query\QueryCache;

final class EcosystemCompatibilityTest extends IntegrationTestCase
{
    public function testCacheIntegration(): void
    {
        $cacheManager = new CacheManager([
            'adapter' => 'memory',
            'ttl' => 3600,
        ]);

        $this->entityManager->setCacheManager($cacheManager);

        // Test de cache des entités
        $user = new User();
        $user->setName('Cached User');
        $user->setEmail('cached@example.com');
        $user->setPassword('password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $userId = $user->getId();

        // Premier accès (mise en cache)
        $cachedUser1 = $this->entityManager->find(User::class, $userId);
        $this->assertNotNull($cachedUser1);

        // Deuxième accès (depuis le cache)
        $cachedUser2 = $this->entityManager->find(User::class, $userId);
        $this->assertSame($cachedUser1, $cachedUser2);

        // Vérifier les métriques de cache
        $stats = $cacheManager->getStatistics();
        $this->assertGreaterThan(0, $stats['hits']);
    }

    public function testEventSystemIntegration(): void
    {
        $eventsFired = [];
        
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener('user.created', function($event) use (&$eventsFired) {
            $eventsFired[] = 'user.created';
        });

        $eventDispatcher->addListener('user.updated', function($event) use (&$eventsFired) {
            $eventsFired[] = 'user.updated';
        });

        $this->entityManager->setEventDispatcher($eventDispatcher);

        // Créer un utilisateur (doit déclencher l'événement)
        $user = new User();
        $user->setName('Event User');
        $user->setEmail('event@example.com');
        $user->setPassword('password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Modifier l'utilisateur (doit déclencher un autre événement)
        $user->setName('Updated Event User');
        $this->entityManager->flush();

        $this->assertContains('user.created', $eventsFired);
        $this->assertContains('user.updated', $eventsFired);
    }

    public function testQueryCacheIntegration(): void
    {
        $queryCache = new QueryCache([
            'adapter' => 'memory',
            'ttl' => 1800,
        ]);

        $this->entityManager->setQueryCache($queryCache);

        // Créer des données de test
        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $user->setName("Query Cache User {$i}");
            $user->setEmail("querycache{$i}@example.com");
            $user->setPassword('password');
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        // Requête qui sera mise en cache
        $qb = $this->entityManager->createQueryBuilder();
        $query = $qb->select('u')
                   ->from(User::class, 'u')
                   ->where('u.active = :active')
                   ->setParameter('active', true)
                   ->getQuery();

        $query->setCacheable(true);
        $query->setCacheLifetime(3600);

        // Premier appel (mise en cache)
        $result1 = $query->getResult();
        
        // Deuxième appel (depuis le cache)
        $result2 = $query->getResult();

        $this->assertEquals($result1, $result2);

        // Vérifier que le cache a été utilisé
        $cacheStats = $queryCache->getStatistics();
        $this->assertGreaterThan(0, $cacheStats['hits']);
    }
}
```

---

**Voir aussi :**
- [Tests unitaires](unit-tests.md)
- [Configuration Docker](docker-setup.md)
- [Utilitaires de test](test-utilities.md)
