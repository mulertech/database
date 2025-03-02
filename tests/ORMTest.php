<?php

namespace MulerTech\Database\Tests;

use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\Tests\Files\Entity\Group;
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

    private function createUserTestTable(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'DROP TABLE IF EXISTS users_test';
        $pdo->exec($query);
        $query = 'DROP TABLE IF EXISTS units_test';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS users_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255), unit_id INT UNSIGNED)';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS units_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))';
        $pdo->exec($query);
    }

    private function createGroupTestTable(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'DROP TABLE IF EXISTS groups_test';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS groups_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), parent_id INT UNSIGNED)';
        $pdo->exec($query);
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

    public function testFindEntityWithOneToOneRelation(): void
    {
        $this->createUserTestTable();
        $em = $this->getEntityManager();
        $unit = new Unit()->setName('JohnUnit');
        $em->persist($unit);
        $createUser = new User()->setUsername('John')->setUnit($unit);
        $em->persist($createUser);
        $em->flush();
        $user = $em->find(User::class, 'username=\'John\'');
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('John', $user->getUsername());
        self::assertEquals('JohnUnit', $user->getUnit()->getName());
        $badUser = $em->find(User::class, 2);
        self::assertNull($badUser);
    }

    public function testFindEntityWithOneToManyRelation(): void
    {
        $this->createGroupTestTable();
        $em = $this->getEntityManager();
        $group1 = new Group();
        $group1->setName('Group1');
        $group2 = new Group();
        $group2->setName('Group2');
        $group2->setParent($group1);
        $group3 = new Group();
        $group3->setName('Group3');
        $group3->setParent($group1);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($group3);
        $em->flush();
        $group = $em->find(Group::class, 'name=\'Group1\'');
        self::assertInstanceOf(Group::class, $group);
        self::assertEquals('Group1', $group->getName());
        self::assertEquals(2, $group->getChildren()->count());
    }

    public function testFindEntityWithManyToOneRelation(): void
    {
        $this->createGroupTestTable();
        $em = $this->getEntityManager();
        $group1 = new Group();
        $group1->setName('Group1');
        $group2 = new Group();
        $group2->setName('Group2');
        $group2->setParent($group1);
        $group3 = new Group();
        $group3->setName('Group3');
        $group3->setParent($group1);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($group3);
        $em->flush();
        $group = $em->find(Group::class, 'name=\'Group2\'');
        self::assertInstanceOf(Group::class, $group);
        self::assertEquals('Group1', $group->getParent()->getName());
    }

    public function testExecuteInsertionsAndPostPersistEvent(): void
    {
        $this->createUserTestTable();
        $em = $this->getEntityManager();
        $this->eventManager->addListener(DbEvents::postPersist->value, static function (PostPersistEvent $event) {
            $user = $event->getEntity();
            $user->setUsername($user->getUsername() . 'UpdatedByEvent')->setUnit(new Unit()->setName('Unit'));
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
        self::assertEquals('Unit', $user->getUnit()->getName());
    }

    public function testExecuteUpdatesAndPostUpdateEvent(): void
    {
        $this->createUserTestTable();
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
            $user->setUsername($user->getUsername() . 'AfterUpdate')->setUnit(new Unit()->setName('Unit'));
            $event->getEntityManager()->flush();
        });
        $user->setUsername('JohnUpdated');
        $em->flush();
        $newUser = $em->find(User::class, 'username=\'BeforeUpdateJohnUpdatedAfterUpdate\'');
        $unit = $em->find(Unit::class, 'name=\'Unit\'');
        self::assertEquals('BeforeUpdateJohnUpdatedAfterUpdate', $newUser->getUsername());
        self::assertEquals('Unit', $newUser->getUnit()->getName());
        self::assertEquals('Unit', $unit->getName());
    }

    public function testExecuteDeletions(): void
    {
        $this->createUserTestTable();
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
        $this->createUserTestTable();
        $em = $this->getEntityManager();
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('INSERT INTO users_test (username) VALUES (:username)');
        $statement->execute(['username' => 'John']);
        $users = $em->read(User::class);
//        $users = $em->read(User::class);
        var_dump($users);
        self::assertCount(1, $users);
        self::assertEquals(1, $users[0]->getId());
        self::assertEquals('John', $users[0]->getUsername());
    }

    public function testReadWithWhere(): void
    {
        $this->createUserTestTable();
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