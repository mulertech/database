<?php

namespace MulerTech\Database\Tests\ORM;

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

class EntityManagerTest extends TestCase
{
    private EventManagerInterface $eventManager;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager();
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new Driver()), []),
            new DbMapping(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'),
            $this->eventManager
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $query = 'DROP TABLE IF EXISTS users_test';
        $this->entityManager->getPdm()->exec($query);
        $query = 'DROP TABLE IF EXISTS units_test';
        $this->entityManager->getPdm()->exec($query);
        $query = 'DROP TABLE IF EXISTS groups_test';
        $this->entityManager->getPdm()->exec($query);
        $query = 'DROP TABLE IF EXISTS link_user_group_test';
        $this->entityManager->getPdm()->exec($query);
    }

    private function createUserTestTable(): void
    {
        $pdo = $this->entityManager->getPdm();
        $query = 'DROP TABLE IF EXISTS users_test';
        $pdo->exec($query);
        $query = 'DROP TABLE IF EXISTS units_test';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS users_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255), size INT, unit_id INT UNSIGNED)';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS units_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))';
        $pdo->exec($query);
    }

    private function createGroupTestTable(): void
    {
        $pdo = $this->entityManager->getPdm();
        $query = 'DROP TABLE IF EXISTS groups_test';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS groups_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), parent_id INT UNSIGNED)';
        $pdo->exec($query);
    }

    private function createLinkUserGroupTestTable(): void
    {
        $pdo = $this->entityManager->getPdm();
        $query = 'DROP TABLE IF EXISTS link_user_group_test';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS link_user_group_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED, group_id INT UNSIGNED)';
        $pdo->exec($query);
    }

    public function testGetRepository(): void
    {
        $em = $this->entityManager;
        $repository = $em->getRepository(User::class);
        self::assertInstanceOf(UserRepository::class, $repository);
    }

    public function testFindEntityWithOneToOneRelation(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
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
        $this->createUserTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        $group1 = new Group();
        $group1->setName('Group1');
        $group2 = new Group();
        $group2->setName('Group2');
        $group2->setParent($group1);
        $group3 = new Group();
        $group3->setName('Group3');
        $group1->addChild($group3);
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
        $this->createUserTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
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

    public function testFindEntityWithManyToManyRelation(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        $group1 = new Group();
        $group1->setName('Group1');
        $group2 = new Group();
        $group2->setName('Group2');
        $user1 = new User();
        $user1->setUsername('User1');
        $user2 = new User();
        $user2->setUsername('User2');
        $user1->addGroup($group1);
        $user1->addGroup($group2);
        $user2->addGroup($group1);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($user1);
        $em->persist($user2);
        $em->flush();
        $newUser1 = $em->find(User::class, 'username=\'User1\'');
        self::assertEquals(
            2,
            count($em->find(User::class, 'username=\'User1\'')->getGroups())
        );
    }

    public function testExecuteInsertionsAndPostPersistEvent(): void
    {
        $this->createUserTestTable();
        $em = $this->entityManager;
        $this->eventManager->addListener(DbEvents::postPersist->value, static function (PostPersistEvent $event) {
            $user = $event->getEntity();
            $user->setUsername($user->getUsername() . 'UpdatedByEvent')->setUnit(new Unit()->setName('Unit'));
        });
        $user = new User();
        $user->setUsername('John');
        $em->persist($user);
        $em->flush();
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('SELECT * FROM users_test');
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        self::assertEquals(['id' => 1, 'username' => 'John', 'size' => null, 'unit_id' => null], $statement->fetch());
        self::assertEquals('JohnUpdatedByEvent', $user->getUsername());
        self::assertEquals('Unit', $user->getUnit()->getName());
    }

    public function testExecuteUpdatesAndPostUpdateEvent(): void
    {
        $this->createUserTestTable();
        $this->createLinkUserGroupTestTable();
        $this->createGroupTestTable();
        $em = $this->entityManager;
        $pdo = $this->entityManager->getPdm();
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
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('INSERT INTO users_test (username) VALUES (:username)');
        $statement->execute(['username' => 'John']);
        $user = $em->find(User::class, 1);
        $em->remove($user);
        $em->flush();
        $statement = $pdo->prepare('SELECT * FROM users_test');
        $statement->execute();
        self::assertFalse($statement->fetch());
    }

    public function testIsUnique(): void
    {
        $this->createUserTestTable();
        $this->createLinkUserGroupTestTable();
        $this->createGroupTestTable();
        $em = $this->entityManager;
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('INSERT INTO users_test (id, username) VALUES (:id, :username)');
        $statement->execute(['id' => 1, 'username' => 'John']);
        $statement = $pdo->prepare('INSERT INTO users_test (id, username) VALUES (:id, :username)');
        $statement->execute(['id' => 2, 'username' => 'Jack']);
        $statement = $pdo->prepare('INSERT INTO users_test (id, username) VALUES (:id, :username)');
        $statement->execute(['id' => 3, 'username' => '898709919412651836']);
        $statement = $pdo->prepare('INSERT INTO users_test (id, username, size) VALUES (:id, :username, :size)');
        $statement->execute(['id' => 4, 'username' => 'Helen', 'size' => 165]);
        self::assertFalse($em->isUnique(User::class, 'username', 'John'));
        self::assertTrue($em->isUnique(User::class, 'username', 'John', 1));
        // If we want to update the username of the user with id 2 from 'another username' to 'John',
        // it should return false
        self::assertFalse($em->isUnique(User::class, 'username', 'John', 2));
        self::assertTrue($em->isUnique(User::class, 'username', 'john', 2, true));
        self::assertFalse($em->isUnique(User::class, 'username', '898709919412651836'));
        self::assertTrue($em->isUnique(User::class, 'username', '898709919412651836', 3));
        // For mysql, 898709919412651836=898709919412651837, the php comparison is required
        self::assertTrue($em->isUnique(User::class, 'username', '898709919412651837'));
        self::assertTrue($em->isUnique(User::class, 'size', '170'));
        self::assertFalse($em->isUnique(User::class, 'size', '165'));
        self::assertTrue($em->isUnique(User::class, 'size', '165', 4));
    }

    public function testRowsCount(): void
    {
        $this->createUserTestTable();
        $em = $this->entityManager;
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('INSERT INTO users_test (id, username) VALUES (:id, :username)');
        $statement->execute(['id' => 1, 'username' => 'John']);
        $statement = $pdo->prepare('INSERT INTO users_test (id, username) VALUES (:id, :username)');
        $statement->execute(['id' => 2, 'username' => 'Jack']);
        self::assertEquals(2, $em->rowCount(User::class));
    }
}