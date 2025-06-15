<?php

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PreRemoveEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\ORM\EntityManager;
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
    private EntityManager $entityManager;

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
        $query = 'CREATE TABLE IF NOT EXISTS users_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255), size INT, unit_id INT UNSIGNED, manager INT UNSIGNED)';
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
        $group3->setParent($group1);
        $group1->addChild($group3);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($group3);
        $em->flush();
        
        $group1 = $em->find(Group::class, 'name=\'Group1\'');
        self::assertNotNull($group1, 'Group1 should be found');
        self::assertInstanceOf(Group::class, $group1);
        self::assertEquals('Group1', $group1->getName());
        self::assertEquals(2, $group1->getChildren()->count());
        
        $group3 = $em->find(Group::class, 'name=\'Group3\'');
        self::assertNotNull($group3, 'Group3 should be found');
        self::assertEquals('Group1', $group3->getParent()->getName());
        
        $group3->setParent(null);
        
        // Check if entity manager detects the change
        $hasChanges = $em->getEmEngine()->hasChanges($group3);
        
        if ($hasChanges) {
            $changes = $em->getEmEngine()->getChanges($group3);
        }
        
        $em->persist($group3);
        $em->flush();
        
        // Check database directly BEFORE trying to find
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('SELECT * FROM groups_test WHERE name = ?');
        $statement->execute(['Group3']);
        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        
        $group3 = $em->find(Group::class, 'name=\'Group3\'');
        if ($group3 === null) {
            // Check database directly
            $statement = $pdo->prepare('SELECT * FROM groups_test WHERE name = ?');
            $statement->execute(['Group3']);
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
        }
        self::assertNotNull($group3, 'Group3 should still be found after update');
        self::assertNull($group3->getParent());
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

    public function testFindEntityWithOneManyToManyRelation(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        
        // Create a unit first (required for User)
        $unit = new Unit();
        $unit->setName('TestUnit');
        $em->persist($unit);
        $em->flush(); // Flush to get the unit ID
        
        $group1 = new Group();
        $group1->setName('Group1');
        $user1 = new User();
        $user1->setUsername('User1');
        $user1->setUnit($unit); // Set the required unit
        $user1->addGroup($group1);
        
        $em->persist($group1);
        $em->persist($user1);
        $em->flush();
        
        // Vérifier que la table pivot contient bien les données
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('SELECT * FROM link_user_group_test');
        $statement->execute();
        $pivotData = $statement->fetchAll(\PDO::FETCH_ASSOC);
        
        self::assertNotEmpty($pivotData, 'La table pivot devrait contenir des données');
        self::assertEquals(1, count($pivotData), 'La table pivot devrait contenir exactement 1 enregistrement');
        self::assertEquals($user1->getId(), $pivotData[0]['user_id']);
        self::assertEquals($group1->getId(), $pivotData[0]['group_id']);
        
        $newUser1 = $em->find(User::class, 'username=\'User1\'');
        $newGroup1 = $em->find(Group::class, 'name=\'Group1\'');
        self::assertEquals('Group1', $newGroup1->getName());
        
        // Use safer access to collections
        $userGroups = $newUser1->getGroups();
        self::assertNotNull($userGroups, 'User groups collection should not be null');
        self::assertGreaterThan(0, $userGroups->count(), 'User should have at least one group');
        
        $firstGroup = $userGroups->reset();
        self::assertNotNull($firstGroup, 'First group should not be null');
        self::assertEquals('Group1', $firstGroup->getName());
    }
    public function testFindEntityWithManyToManyRelations(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        
        // Create a unit first (required for User)
        $unit = new Unit();
        $unit->setName('TestUnit');
        $em->persist($unit);
        $em->flush(); // Flush to get the unit ID
        
        $group1 = new Group();
        $group1->setName('Group1');
        $group2 = new Group();
        $group2->setName('Group2');
        $user1 = new User();
        $user1->setUsername('User1');
        $user1->setUnit($unit); // Set the required unit
        $user1->addGroup($group1);
        $user1->addGroup($group2);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($user1);
        $em->flush();
        $newUser1 = $em->find(User::class, 'username=\'User1\'');
        self::assertEquals(
            2,
            count($newUser1->getGroups())
        );
    }

    public function testFindEntitiesWithManyToManyRelationAndRemoveOne(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        
        // Create a unit first (required for User)
        $unit = new Unit();
        $unit->setName('TestUnit');
        $em->persist($unit);
        $em->flush(); // Flush to get the unit ID
        
        $group1 = new Group();
        $group1->setName('Group1');
        $group2 = new Group();
        $group2->setName('Group2');
        $user1 = new User();
        $user1->setUsername('User1');
        $user1->setUnit($unit); // Set the required unit
        $user1->addGroup($group1);
        $user1->addGroup($group2);
        $user2 = new User();
        $user2->setUsername('User2');
        $user2->setUnit($unit); // Set the required unit
        $user2->addGroup($group1);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($user1);
        $em->persist($user2);
        $em->flush();
        
        $newUser1 = $em->find(User::class, 'username=\'User1\'');
        self::assertEquals(2, count($newUser1->getGroups()));
        
        $newUser2 = $em->find(User::class, 'username=\'User2\'');
        
        $newUser2->addGroup($group2);
        
        $newUser2->removeGroup($group1);
        
        $em->persist($newUser2);
        $em->flush();
        
        // Check database directly
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('SELECT * FROM link_user_group_test WHERE user_id = ?');
        $statement->execute([$newUser2->getId()]);
        $pivotData = $statement->fetchAll(\PDO::FETCH_ASSOC);
        
        // Reload to get fresh state
        $reloadedUser2 = $em->find(User::class, 'username=\'User2\'');
        
        $firstGroup = $reloadedUser2->getGroups()->reset();
        
        self::assertEquals(1, count($reloadedUser2->getGroups()));
        self::assertEquals('Group2', $reloadedUser2->getGroups()->reset()->getName());
    }

    public function testExecuteInsertionsAndPostPersistEvent(): void
    {
        $this->createUserTestTable();
        $em = $this->entityManager;
        
        // Create a unit first
        $unit = new Unit();
        $unit->setName('Unit');
        $em->persist($unit);
        $em->flush();
        
        $this->eventManager->addListener(DbEvents::postPersist->value, static function (PostPersistEvent $event) use ($unit) {
            $user = $event->getEntity();
            $user->setUsername($user->getUsername() . 'UpdatedByEvent')->setUnit($unit);
        });
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit); // Set required unit
        $em->persist($user);
        $em->flush();
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('SELECT * FROM users_test');
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetch();
        self::assertEquals('John', $result['username']); // Database should still have original value
        self::assertEquals('JohnUpdatedByEvent', $user->getUsername()); // Object should be updated
        self::assertEquals('Unit', $user->getUnit()->getName());
    }

    public function testExecuteInsertionsAndPostFlushEvent(): void
    {
        $this->createUserTestTable();
        $this->createLinkUserGroupTestTable();
        $this->createGroupTestTable();
        $em = $this->entityManager;
        
        // Create a unit first
        $unit = new Unit();
        $unit->setName('TestUnit');
        $em->persist($unit);
        $em->flush();
        
        $this->eventManager->addListener(DbEvents::postFlush->value, static function (PostFlushEvent $event) use ($unit) {
            $jane = new User()->setUsername('Jane')->setUnit($unit);
            $john = $event->getEntityManager()->find(User::class, 'username=\'John\'');
            if ($john !== null) {
                $jane->setManager($john);
            }
            $event->getEntityManager()->persist($jane);
            $event->getEntityManager()->flush();
        });
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        $em->persist($user);
        $em->flush();
        $jane = $em->find(User::class, 'username=\'Jane\'');
        $john = $em->find(User::class, 'username=\'John\'');
        self::assertNotNull($jane, 'Jane should have been created');
        self::assertNotNull($john, 'John should exist');
        self::assertEquals('John', $john->getUsername());
        self::assertEquals('John', $jane->getManager()->getUsername());
    }

    public function testExecuteUpdatesAndPostUpdateEvent(): void
    {
        $this->createUserTestTable();
        $this->createLinkUserGroupTestTable();
        $this->createGroupTestTable();
        $em = $this->entityManager;
        
        // Create a unit first
        $unit = new Unit();
        $unit->setName('TestUnit');
        $em->persist($unit);
        $em->flush();
        
        $pdo = $this->entityManager->getPdm();
        $statement = $pdo->prepare('INSERT INTO users_test (username, unit_id) VALUES (:username, :unit_id)');
        $statement->execute(['username' => 'John', 'unit_id' => $unit->getId()]);
        
        $user = $em->find(User::class, 1);
        self::assertNotNull($user, 'User should be found');
        
        $this->eventManager->addListener(DbEvents::preUpdate->value, static function (PreUpdateEvent $event) {
            $user = $event->getEntity();
            $update = $event->getEntityChanges();
            $updateTag = implode('->', $update['username']);
            $user->setUsername('BeforeUpdate' . $updateTag . $user->getUsername());
        });
        
        $this->eventManager->addListener(DbEvents::postUpdate->value, static function (PostUpdateEvent $event) {
            $user = $event->getEntity();
            $unit = new Unit()->setName('Unit');
            $event->getEntityManager()->persist($unit);
            $user->setUsername($user->getUsername() . 'AfterUpdate')->setUnit($unit);
            $event->getEntityManager()->flush();
        });
        
        $user->setUsername('JohnUpdated');
        $em->flush();
        
        $newUser = $em->find(User::class, 1);
        self::assertNotNull($newUser, 'User should still exist');
        self::assertNotNull($newUser->getUnit(), 'User should have a unit');
        
        $unit = $em->find(Unit::class, 'name=\'Unit\'');
        self::assertNotNull($unit, 'Unit should exist');
        self::assertEquals('BeforeUpdateJohn->JohnUpdatedJohnUpdatedAfterUpdate', $newUser->getUsername());
        self::assertEquals('Unit', $newUser->getUnit()->getName());
        self::assertEquals('Unit', $unit->getName());
    }

    public function testExecuteDeletionsAndPreRemoveEvent(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        
        // Create a unit first
        $unit = new Unit();
        $unit->setName('TestUnit');
        $em->persist($unit);
        $em->flush();
        
        $manager = new User()->setUsername('Manager')->setUnit($unit);
        $otherManager = new User()->setUsername('OtherManager')->setUnit($unit);
        $user = new User()->setUsername('John')->setManager($manager)->setUnit($unit);
        $em->persist($manager);
        $em->persist($otherManager);
        $em->persist($user);
        $em->flush();
        $this->eventManager->addListener(DbEvents::preRemove->value, static function (PreRemoveEvent $event) {
            $manager = $event->getEntity();
            $otherManager = $event->getEntityManager()->find(User::class, 'username=\'OtherManager\'');
            $user = $event->getEntityManager()->find(User::class, 'username=\'John\'');
            if ($user !== null && $otherManager !== null) {
                $user->setManager($otherManager);
                $event->getEntityManager()->persist($user);
                $event->getEntityManager()->flush();
            }
        });
        $em->remove($manager);
        $em->flush();
        $user = $em->find(User::class, 'username=\'John\'');
        self::assertNotNull($user, 'User should still exist');
        self::assertNotNull($user->getManager(), 'User should have a manager');
        self::assertEquals('OtherManager', $user->getManager()->getUsername());
    }

    public function testExecuteDeletionsAndPostRemoveEvent(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        
        // Create a unit first
        $unit = new Unit();
        $unit->setName('TestUnit');
        $em->persist($unit);
        $em->flush();
        
        $manager = new User()->setUsername('Manager')->setUnit($unit);
        $otherManager = new User()->setUsername('OtherManager')->setUnit($unit);
        $user = new User()->setUsername('John')->setManager($manager)->setUnit($unit);
        $em->persist($manager);
        $em->persist($otherManager);
        $em->persist($user);
        $em->flush();
        $this->eventManager->addListener(DbEvents::postRemove->value, static function (PostRemoveEvent $event) {
            $manager = $event->getEntity();
            $otherManager = $event->getEntityManager()->find(User::class, 'username=\'OtherManager\'');
            $user = $event->getEntityManager()->find(User::class, 'username=\'John\'');
            if ($user !== null && $otherManager !== null) {
                $user->setManager($otherManager);
                $event->getEntityManager()->persist($user);
                $event->getEntityManager()->flush();
            }
        });
        $em->remove($manager);
        $em->flush();
        $user = $em->find(User::class, 'username=\'John\'');
        self::assertNotNull($user, 'User should still exist');
        self::assertNotNull($user->getManager(), 'User should have a manager');
        self::assertEquals('OtherManager', $user->getManager()->getUsername());
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