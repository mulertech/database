# Tests Unitaires

Guide pour écrire et exécuter des tests unitaires avec MulerTech Database.

## Table des Matières
- [Configuration des tests](#configuration-des-tests)
- [Tests des entités](#tests-des-entités)
- [Tests des repositories](#tests-des-repositories)
- [Tests du QueryBuilder](#tests-du-querybuilder)
- [Mocking et fixtures](#mocking-et-fixtures)
- [Tests de performance](#tests-de-performance)

## Configuration des tests

### Configuration PHPUnit

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/Migrations</directory>
        </exclude>
    </coverage>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_DATABASE" value="mulertech_test"/>
        <env name="DB_HOST" value="127.0.0.1"/>
        <env name="DB_USERNAME" value="test"/>
        <env name="DB_PASSWORD" value="test"/>
    </php>
</phpunit>
```

### Bootstrap des tests

```php
<?php
// tests/bootstrap.php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use MulerTech\Database\EntityManager;
use MulerTech\Database\Configuration\Configuration;

// Configuration de test
$config = new Configuration([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'mulertech_test',
    'username' => $_ENV['DB_USERNAME'] ?? 'test',
    'password' => $_ENV['DB_PASSWORD'] ?? 'test',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

// EntityManager global pour les tests
$GLOBALS['entityManager'] = new EntityManager($config);

// Créer le schéma de test
$GLOBALS['entityManager']->getSchemaManager()->createSchema();
```

### Classe de base pour les tests

```php
<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use MulerTech\Database\EntityManager;
use MulerTech\Database\Connection\ConnectionInterface;

abstract class DatabaseTestCase extends TestCase
{
    protected EntityManager $entityManager;
    protected ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $GLOBALS['entityManager'];
        $this->connection = $this->entityManager->getConnection();
        
        // Commencer une transaction pour isolation
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback pour nettoyer après chaque test
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollback();
        }
        
        parent::tearDown();
    }

    protected function createUser(array $data = []): User
    {
        $user = new User();
        $user->setEmail($data['email'] ?? 'test@example.com');
        $user->setName($data['name'] ?? 'Test User');
        $user->setPassword($data['password'] ?? 'password123');
        
        return $user;
    }

    protected function persistAndFlush(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    protected function assertDatabaseHas(string $table, array $criteria): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('COUNT(*) as count')
                    ->from($table);

        foreach ($criteria as $field => $value) {
            $queryBuilder->andWhere("{$field} = :{$field}")
                        ->setParameter($field, $value);
        }

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        
        $this->assertGreaterThan(0, $result, "No record found in table '{$table}' with criteria: " . json_encode($criteria));
    }

    protected function assertDatabaseMissing(string $table, array $criteria): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('COUNT(*) as count')
                    ->from($table);

        foreach ($criteria as $field => $value) {
            $queryBuilder->andWhere("{$field} = :{$field}")
                        ->setParameter($field, $value);
        }

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        
        $this->assertEquals(0, $result, "Unexpected record found in table '{$table}' with criteria: " . json_encode($criteria));
    }
}
```

## Tests des entités

### Test d'une entité simple

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use App\Entity\User;
use DateTime;

final class UserTest extends TestCase
{
    public function testUserCanBeCreated(): void
    {
        $user = new User();
        
        $this->assertInstanceOf(User::class, $user);
        $this->assertNull($user->getId());
    }

    public function testUserPropertiesCanBeSet(): void
    {
        $user = new User();
        $email = 'test@example.com';
        $name = 'John Doe';
        $password = 'securepassword';

        $user->setEmail($email);
        $user->setName($name);
        $user->setPassword($password);

        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($name, $user->getName());
        $this->assertEquals($password, $user->getPassword());
    }

    public function testUserTimestampsAreSetAutomatically(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('John Doe');
        
        // Simuler l'appel des callbacks d'événements
        $user->prePersist();
        
        $this->assertInstanceOf(DateTime::class, $user->getCreatedAt());
        $this->assertInstanceOf(DateTime::class, $user->getUpdatedAt());
    }

    public function testUserEmailValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $user = new User();
        $user->setEmail('invalid-email');
    }

    public function testUserToArray(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('John Doe');

        $array = $user->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('test@example.com', $array['email']);
        $this->assertEquals('John Doe', $array['name']);
        $this->assertArrayNotHasKey('password', $array); // Password should be excluded
    }
}
```

### Test des relations

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Tests\DatabaseTestCase;
use App\Entity\User;
use App\Entity\Post;
use App\Entity\Category;

final class UserRelationsTest extends DatabaseTestCase
{
    public function testUserCanHavePosts(): void
    {
        $user = $this->createUser();
        $this->persistAndFlush($user);

        $post1 = new Post();
        $post1->setTitle('First Post');
        $post1->setContent('Content of first post');
        $post1->setUser($user);

        $post2 = new Post();
        $post2->setTitle('Second Post');
        $post2->setContent('Content of second post');
        $post2->setUser($user);

        $this->entityManager->persist($post1);
        $this->entityManager->persist($post2);
        $this->entityManager->flush();

        // Recharger l'utilisateur pour tester le lazy loading
        $this->entityManager->clear();
        $reloadedUser = $this->entityManager->find(User::class, $user->getId());

        $this->assertCount(2, $reloadedUser->getPosts());
        $this->assertEquals('First Post', $reloadedUser->getPosts()[0]->getTitle());
        $this->assertEquals('Second Post', $reloadedUser->getPosts()[1]->getTitle());
    }

    public function testPostBelongsToCategory(): void
    {
        $category = new Category();
        $category->setName('Technology');
        $this->persistAndFlush($category);

        $user = $this->createUser();
        $this->persistAndFlush($user);

        $post = new Post();
        $post->setTitle('Tech Post');
        $post->setContent('A post about technology');
        $post->setUser($user);
        $post->setCategory($category);

        $this->persistAndFlush($post);

        $this->assertDatabaseHas('posts', [
            'title' => 'Tech Post',
            'category_id' => $category->getId()
        ]);
    }

    public function testCascadingDelete(): void
    {
        $user = $this->createUser();
        $this->persistAndFlush($user);

        $post = new Post();
        $post->setTitle('Test Post');
        $post->setContent('Test content');
        $post->setUser($user);
        $this->persistAndFlush($post);

        $postId = $post->getId();

        // Supprimer l'utilisateur doit supprimer ses posts (si cascade configuré)
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->assertDatabaseMissing('posts', ['id' => $postId]);
        $this->assertDatabaseMissing('users', ['id' => $user->getId()]);
    }
}
```

## Tests des repositories

### Test d'un repository personnalisé

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Tests\DatabaseTestCase;
use App\Entity\User;
use App\Repository\UserRepository;

final class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->entityManager->getRepository(User::class);
    }

    public function testFindByEmail(): void
    {
        $user = $this->createUser(['email' => 'unique@example.com']);
        $this->persistAndFlush($user);

        $foundUser = $this->userRepository->findByEmail('unique@example.com');

        $this->assertNotNull($foundUser);
        $this->assertEquals('unique@example.com', $foundUser->getEmail());
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $foundUser = $this->userRepository->findByEmail('nonexistent@example.com');

        $this->assertNull($foundUser);
    }

    public function testFindActiveUsers(): void
    {
        // Créer des utilisateurs actifs et inactifs
        $activeUser1 = $this->createUser(['email' => 'active1@example.com']);
        $activeUser1->setActive(true);
        
        $activeUser2 = $this->createUser(['email' => 'active2@example.com']);
        $activeUser2->setActive(true);
        
        $inactiveUser = $this->createUser(['email' => 'inactive@example.com']);
        $inactiveUser->setActive(false);

        $this->persistAndFlush($activeUser1);
        $this->persistAndFlush($activeUser2);
        $this->persistAndFlush($inactiveUser);

        $activeUsers = $this->userRepository->findActiveUsers();

        $this->assertCount(2, $activeUsers);
        $this->assertTrue($activeUsers[0]->isActive());
        $this->assertTrue($activeUsers[1]->isActive());
    }

    public function testFindUsersCreatedAfter(): void
    {
        $cutoffDate = new DateTime('2024-01-01');
        
        $oldUser = $this->createUser(['email' => 'old@example.com']);
        $oldUser->setCreatedAt(new DateTime('2023-12-01'));
        
        $newUser = $this->createUser(['email' => 'new@example.com']);
        $newUser->setCreatedAt(new DateTime('2024-06-01'));

        $this->persistAndFlush($oldUser);
        $this->persistAndFlush($newUser);

        $recentUsers = $this->userRepository->findUsersCreatedAfter($cutoffDate);

        $this->assertCount(1, $recentUsers);
        $this->assertEquals('new@example.com', $recentUsers[0]->getEmail());
    }

    public function testSearchUsersByName(): void
    {
        $user1 = $this->createUser(['name' => 'John Smith', 'email' => 'john@example.com']);
        $user2 = $this->createUser(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        $user3 = $this->createUser(['name' => 'Bob Johnson', 'email' => 'bob@example.com']);

        $this->persistAndFlush($user1);
        $this->persistAndFlush($user2);
        $this->persistAndFlush($user3);

        $smithUsers = $this->userRepository->searchByName('Smith');

        $this->assertCount(2, $smithUsers);
        
        $names = array_map(fn($user) => $user->getName(), $smithUsers);
        $this->assertContains('John Smith', $names);
        $this->assertContains('Jane Smith', $names);
    }

    public function testGetUserStatistics(): void
    {
        // Créer différents types d'utilisateurs
        for ($i = 0; $i < 5; $i++) {
            $user = $this->createUser(['email' => "user{$i}@example.com"]);
            $user->setActive($i < 3); // 3 actifs, 2 inactifs
            $this->persistAndFlush($user);
        }

        $stats = $this->userRepository->getUserStatistics();

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['active']);
        $this->assertEquals(2, $stats['inactive']);
    }
}
```

## Tests du QueryBuilder

### Test des requêtes de base

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Query;

use Tests\DatabaseTestCase;
use App\Entity\User;
use App\Entity\Post;

final class QueryBuilderTest extends DatabaseTestCase
{
    public function testSimpleSelect(): void
    {
        $user = $this->createUser();
        $this->persistAndFlush($user);

        $qb = $this->entityManager->createQueryBuilder();
        $users = $qb->select('u')
                    ->from(User::class, 'u')
                    ->where('u.email = :email')
                    ->setParameter('email', $user->getEmail())
                    ->getQuery()
                    ->getResult();

        $this->assertCount(1, $users);
        $this->assertEquals($user->getEmail(), $users[0]->getEmail());
    }

    public function testJoinQuery(): void
    {
        $user = $this->createUser();
        $this->persistAndFlush($user);

        $post = new Post();
        $post->setTitle('Test Post');
        $post->setContent('Test content');
        $post->setUser($user);
        $this->persistAndFlush($post);

        $qb = $this->entityManager->createQueryBuilder();
        $result = $qb->select('u', 'p')
                    ->from(User::class, 'u')
                    ->join('u.posts', 'p')
                    ->where('p.title = :title')
                    ->setParameter('title', 'Test Post')
                    ->getQuery()
                    ->getResult();

        $this->assertCount(1, $result);
        $this->assertEquals($user->getEmail(), $result[0]->getEmail());
        $this->assertCount(1, $result[0]->getPosts());
    }

    public function testAggregateQuery(): void
    {
        // Créer plusieurs utilisateurs
        for ($i = 0; $i < 3; $i++) {
            $user = $this->createUser(['email' => "user{$i}@example.com"]);
            $this->persistAndFlush($user);
        }

        $qb = $this->entityManager->createQueryBuilder();
        $count = $qb->select('COUNT(u.id)')
                   ->from(User::class, 'u')
                   ->getQuery()
                   ->getSingleScalarResult();

        $this->assertEquals(3, $count);
    }

    public function testOrderByAndLimit(): void
    {
        // Créer des utilisateurs avec des noms différents
        $names = ['Alice', 'Bob', 'Charlie'];
        foreach ($names as $name) {
            $user = $this->createUser([
                'name' => $name,
                'email' => strtolower($name) . '@example.com'
            ]);
            $this->persistAndFlush($user);
        }

        $qb = $this->entityManager->createQueryBuilder();
        $users = $qb->select('u')
                   ->from(User::class, 'u')
                   ->orderBy('u.name', 'ASC')
                   ->setMaxResults(2)
                   ->getQuery()
                   ->getResult();

        $this->assertCount(2, $users);
        $this->assertEquals('Alice', $users[0]->getName());
        $this->assertEquals('Bob', $users[1]->getName());
    }

    public function testComplexWhere(): void
    {
        // Créer des utilisateurs avec différents attributs
        $user1 = $this->createUser(['name' => 'John', 'email' => 'john@example.com']);
        $user1->setActive(true);
        
        $user2 = $this->createUser(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user2->setActive(false);
        
        $user3 = $this->createUser(['name' => 'Bob', 'email' => 'bob@example.com']);
        $user3->setActive(true);

        $this->persistAndFlush($user1);
        $this->persistAndFlush($user2);
        $this->persistAndFlush($user3);

        $qb = $this->entityManager->createQueryBuilder();
        $users = $qb->select('u')
                   ->from(User::class, 'u')
                   ->where('u.active = :active')
                   ->andWhere('u.name LIKE :namePattern')
                   ->setParameter('active', true)
                   ->setParameter('namePattern', 'J%')
                   ->getQuery()
                   ->getResult();

        $this->assertCount(1, $users);
        $this->assertEquals('John', $users[0]->getName());
    }
}
```

## Mocking et fixtures

### Factory pour créer des entités de test

```php
<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Entity\User;
use App\Entity\Post;
use App\Entity\Category;
use DateTime;

class EntityFactory
{
    public static function createUser(array $overrides = []): User
    {
        $user = new User();
        
        $defaults = [
            'name' => 'Test User',
            'email' => 'test' . uniqid() . '@example.com',
            'password' => 'password123',
            'active' => true,
            'createdAt' => new DateTime(),
            'updatedAt' => new DateTime(),
        ];

        $data = array_merge($defaults, $overrides);

        $user->setName($data['name']);
        $user->setEmail($data['email']);
        $user->setPassword($data['password']);
        $user->setActive($data['active']);
        $user->setCreatedAt($data['createdAt']);
        $user->setUpdatedAt($data['updatedAt']);

        return $user;
    }

    public static function createPost(User $user = null, array $overrides = []): Post
    {
        $post = new Post();
        
        $defaults = [
            'title' => 'Test Post ' . uniqid(),
            'content' => 'This is test content for the post.',
            'published' => true,
            'createdAt' => new DateTime(),
        ];

        $data = array_merge($defaults, $overrides);

        $post->setTitle($data['title']);
        $post->setContent($data['content']);
        $post->setPublished($data['published']);
        $post->setCreatedAt($data['createdAt']);

        if ($user) {
            $post->setUser($user);
        }

        return $post;
    }

    public static function createCategory(array $overrides = []): Category
    {
        $category = new Category();
        
        $defaults = [
            'name' => 'Test Category ' . uniqid(),
            'description' => 'Test category description',
        ];

        $data = array_merge($defaults, $overrides);

        $category->setName($data['name']);
        $category->setDescription($data['description']);

        return $category;
    }
}
```

### Fixtures pour les tests

```php
<?php

declare(strict_types=1);

namespace Tests\Fixture;

use Tests\DatabaseTestCase;
use Tests\Factory\EntityFactory;
use App\Entity\User;
use App\Entity\Post;
use App\Entity\Category;

class DatabaseFixture
{
    private $entityManager;

    public function __construct($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function loadUserFixtures(): array
    {
        $users = [];

        // Créer des utilisateurs avec des données variées
        $userData = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'active' => true],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'active' => true],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'active' => false],
        ];

        foreach ($userData as $data) {
            $user = EntityFactory::createUser($data);
            $this->entityManager->persist($user);
            $users[] = $user;
        }

        $this->entityManager->flush();
        return $users;
    }

    public function loadBlogFixtures(): array
    {
        $users = $this->loadUserFixtures();
        
        // Créer des catégories
        $categories = [
            EntityFactory::createCategory(['name' => 'Technology']),
            EntityFactory::createCategory(['name' => 'Science']),
            EntityFactory::createCategory(['name' => 'Sports']),
        ];

        foreach ($categories as $category) {
            $this->entityManager->persist($category);
        }

        // Créer des posts
        $posts = [];
        for ($i = 0; $i < 10; $i++) {
            $user = $users[array_rand($users)];
            $category = $categories[array_rand($categories)];
            
            $post = EntityFactory::createPost($user, [
                'title' => "Post #{$i}",
                'content' => "Content for post number {$i}",
            ]);
            $post->setCategory($category);
            
            $this->entityManager->persist($post);
            $posts[] = $post;
        }

        $this->entityManager->flush();
        
        return [
            'users' => $users,
            'categories' => $categories,
            'posts' => $posts,
        ];
    }
}
```

## Tests de performance

### Test de performance des requêtes

```php
<?php

declare(strict_types=1);

namespace Tests\Performance;

use Tests\DatabaseTestCase;
use Tests\Factory\EntityFactory;
use App\Entity\User;

final class QueryPerformanceTest extends DatabaseTestCase
{
    public function testLargeDatasetQuery(): void
    {
        // Créer un grand nombre d'utilisateurs
        $users = [];
        for ($i = 0; $i < 1000; $i++) {
            $users[] = EntityFactory::createUser([
                'email' => "user{$i}@example.com",
                'name' => "User {$i}"
            ]);
        }

        $startTime = microtime(true);
        
        foreach ($users as $user) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $insertTime = microtime(true) - $startTime;
        
        // Test de requête sur le grand dataset
        $startTime = microtime(true);
        
        $qb = $this->entityManager->createQueryBuilder();
        $result = $qb->select('u')
                    ->from(User::class, 'u')
                    ->where('u.name LIKE :pattern')
                    ->setParameter('pattern', 'User 5%')
                    ->getQuery()
                    ->getResult();

        $queryTime = microtime(true) - $startTime;

        // Assertions sur les performances
        $this->assertLessThan(2.0, $insertTime, 'Insert time should be less than 2 seconds');
        $this->assertLessThan(0.1, $queryTime, 'Query time should be less than 100ms');
        $this->assertGreaterThan(0, count($result), 'Query should return results');
    }

    public function testQueryBuilderPerformance(): void
    {
        // Créer des données de test
        for ($i = 0; $i < 100; $i++) {
            $user = EntityFactory::createUser(['email' => "perf{$i}@example.com"]);
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $startTime = microtime(true);

        // Exécuter de nombreuses requêtes
        for ($i = 0; $i < 50; $i++) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('u')
               ->from(User::class, 'u')
               ->where('u.id = :id')
               ->setParameter('id', $i + 1)
               ->getQuery()
               ->getOneOrNullResult();
        }

        $totalTime = microtime(true) - $startTime;
        $avgTime = $totalTime / 50;

        $this->assertLessThan(0.01, $avgTime, 'Average query time should be less than 10ms');
    }

    /**
     * @group memory
     */
    public function testMemoryUsage(): void
    {
        $initialMemory = memory_get_usage(true);

        // Créer et persister de nombreuses entités
        for ($i = 0; $i < 500; $i++) {
            $user = EntityFactory::createUser(['email' => "memory{$i}@example.com"]);
            $this->entityManager->persist($user);
            
            // Flush périodiquement pour éviter l'accumulation
            if ($i % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $finalMemory = memory_get_usage(true);
        
        $memoryIncrease = $finalMemory - $initialMemory;
        $memoryIncreaseKB = $memoryIncrease / 1024;

        // La consommation mémoire ne doit pas dépasser 10MB
        $this->assertLessThan(10240, $memoryIncreaseKB, 'Memory increase should be less than 10MB');
    }
}
```

---

**Voir aussi :**
- [Tests d'intégration](integration-tests.md)
- [Configuration Docker](docker-setup.md)
- [Utilitaires de test](test-utilities.md)
