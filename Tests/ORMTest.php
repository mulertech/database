<?php

namespace MulerTech\Database\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;
use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\Tests\Files\DoctrineTest\Unittest;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Repository\UserRepository;
use MulerTech\EventManager\EventManager;
use MulerTech\EventManager\EventManagerInterface;
use PDO;
use PHPUnit\Framework\TestCase;

class ORMTest extends TestCase
{
    private EventManagerInterface $eventManager;

    private function getPhpDatabaseManager(): PhpDatabaseManager
    {
        return new PhpDatabaseManager(new PdoConnector(new Driver()), []);
    }

    private function createTestTable(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'DROP TABLE IF EXISTS users_test';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS users_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255), unit_id INT UNSIGNED)';
        $pdo->exec($query);
    }

    private function createDoctrineTestTables(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'DROP TABLE IF EXISTS usertest';
        $pdo->exec($query);
        $query = 'DROP TABLE IF EXISTS unittest';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS usertest (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255), unittest_id INT UNSIGNED)';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS unittest (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), parent_id INT UNSIGNED)';
        $pdo->exec($query);
//        $statement = $pdo->prepare('INSERT INTO usertest (id, username, unittest_id) VALUES (:id, :username, :unittest_id)');
//        $statement->execute(['id' => 1, 'username' => 'John', 'unittest_id' => 33]);
//        $statement = $pdo->prepare('INSERT INTO unittest (id, name) VALUES (:id, :name)');
//        $statement->execute(['id' => 33, 'name' => 'Unité']);
    }

    public function getEntityManager(): EntityManagerInterface
    {
        $this->eventManager = new EventManager();
        return new EntityManager(
            $this->getPhpDatabaseManager(),
            new DbMapping(__DIR__ . '/Files/Entity'),
            $this->eventManager
        );
    }

    public function testGetRepository(): void
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository(User::class);
        self::assertInstanceOf(UserRepository::class, $repository);
    }

    public function testFind(): void
    {
        $this->createTestTable();
        $em = $this->getEntityManager();
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('INSERT INTO users_test (username, unit_id) VALUES (:username, :unit_id)');
        $statement->execute(['username' => 'John', 'unit_id' => 33]);
        $user = $em->find(User::class, 1);
        // tests
        var_dump($user);
        $user->setUnit(fn ($em) => $em->find(Unit::class, 33));
        var_dump($user);






        self::assertInstanceOf(User::class, $user);
        self::assertEquals(1, $user->getId());
        self::assertEquals('John', $user->getUsername());
//        self::assertEquals(33, $user->getUnit());
        $badUser = $em->find(User::class, 2);
        self::assertNull($badUser);
    }

    public function testToDeleteDoctrine()
    {
        $this->createDoctrineTestTables();
        $entitiesPath = [__DIR__ . '/Files/DoctrineTest'];
        $dbParams = [
            'driver' => 'pdo_mysql',
            'host' => 'db',
            'user' => 'username',
            'password' => 'password',
            'dbname' => 'dbname',
        ];
        $config = ORMSetup::createAttributeMetadataConfiguration($entitiesPath, true);
        $connection = DriverManager::getConnection($dbParams, $config);
        $em = new \Doctrine\ORM\EntityManager($connection, $config);
//        $user = $em->find(\MulerTech\Database\Tests\Files\DoctrineTest\Usertest::class, 1);
//        var_dump($user);
        //
        $em->flush();
        $parent = new Unittest();
        $parent->setName('Parent');
        $em->persist($parent);
        $em->flush();
        $unittest = new Unittest();
        $unittest->setName('Unité');
        $unittest->setParent($parent);
        $em->persist($unittest);
        $em->flush();
        $user = new \MulerTech\Database\Tests\Files\DoctrineTest\Usertest();
        $user->setUsername('John');
        $user->setUnittest($unittest);
        $em->persist($user);
        $em->flush();



        $user = $em->find(\MulerTech\Database\Tests\Files\DoctrineTest\Usertest::class, 1);
        var_dump($user);
    }

    public function testExecuteInsertionsAndPostPersistEvent(): void
    {
        $this->createTestTable();
        $em = $this->getEntityManager();
        $this->eventManager->addListener(DbEvents::postPersist->value, static function (PostPersistEvent $event) {
            $user = $event->getEntity();
            $user->setUsername($user->getUsername() . 'UpdatedByEvent')->setUnit(33806);
        });
        $user = new User();
        $user->setUsername('John');
        $em->persist($user);
        $em->flush();
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('SELECT * FROM users_test');
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        self::assertEquals(['id' => 1, 'username' => 'John', 'unit_id' => null], $statement->fetch());
        self::assertEquals('JohnUpdatedByEvent', $user->getUsername());
        self::assertEquals(33806, $user->getUnit());
    }

    public function testExecuteUpdatesAndPostUpdateEvent(): void
    {
        $this->createTestTable();
        $em = $this->getEntityManager();
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('INSERT INTO users_test (username) VALUES (:username)');
        $statement->execute(['username' => 'John']);
        $user = $em->find(User::class, 1);
        $this->eventManager->addListener(DbEvents::preUpdate->value, static function (PreUpdateEvent $event) {
            $user = $event->getEntity();
            $user->setUsername('BeforeUpdate' . $user->getUsername());
            $event->getEntityManager()->flush();
        });
        $this->eventManager->addListener(DbEvents::postUpdate->value, static function (PostUpdateEvent $event) {
            $user = $event->getEntity();
            $user->setUsername($user->getUsername() . 'AfterUpdate')->setUnit(33806);
            $event->getEntityManager()->flush();
        });
        $user->setUsername('JohnUpdated');
        $em->flush();
        $statement = $pdo->prepare('SELECT * FROM users_test');
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        self::assertEquals(['id' => 1, 'username' => 'BeforeUpdateJohnUpdatedAfterUpdate', 'unit_id' => 33806], $statement->fetch());
    }

    public function testExecuteDeletions(): void
    {
        $this->createTestTable();
        $em = $this->getEntityManager();
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('INSERT INTO users_test (username) VALUES (:username)');
        $statement->execute(['username' => 'John']);
        $user = $em->find(User::class, 1);
        $em->remove($user);
        $em->flush();
        $statement = $pdo->prepare('SELECT * FROM users_test');
        $statement->execute();
        self::assertFalse($statement->fetch());
    }

    public function testRead(): void
    {
        $this->createTestTable();
        $em = $this->getEntityManager();
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('INSERT INTO users_test (username) VALUES (:username)');
        $statement->execute(['username' => 'John']);
        $users = $em->read('users_test');
//        $users = $em->read(User::class);
        var_dump($users);
        self::assertCount(1, $users);
        self::assertEquals(1, $users[0]->getId());
        self::assertEquals('John', $users[0]->getUsername());
    }

    public function testReadWithWhere(): void
    {
        $this->createTestTable();
        $em = $this->getEntityManager();
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('INSERT INTO users_test (username) VALUES (:username)');
        $statement->execute(['username' => 'John']);
        $statement->execute(['username' => 'Jane']);
        $users = $em->read(User::class, ['username' => 'Jane']);
        self::assertCount(1, $users);
        self::assertInstanceOf(User::class, $users[0]);
        self::assertEquals(2, $users[0]->getId());
        self::assertEquals('Jane', $users[0]->getUsername());
    }
}