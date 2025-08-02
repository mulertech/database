<?php

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PreRemoveEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Query\Builder\QueryBuilder;
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
        $metadataCache = new MetadataCache();
        // Load entities from the test directory
        $metadataCache->loadEntitiesFromPath(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
        );
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $metadataCache,
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
        self::assertEquals(User::class, $repository->getEntityName());

        // Sous-classe anonyme pour exposer createQueryBuilder
        $publicRepository = new class($em) extends UserRepository {
            public function publicCreateQueryBuilder()
            {
                return $this->createQueryBuilder();
            }
        };

        $queryBuilder = $publicRepository->publicCreateQueryBuilder();
        self::assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    public function testFindEntityWithOneToOneRelation(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        
        // Verify tables are empty initially
        $stmt = $this->entityManager->getPdm()->prepare('SELECT COUNT(*) as count FROM users_test');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertEquals(0, $result['count'], 'Users table should be empty initially');
        
        $stmt = $this->entityManager->getPdm()->prepare('SELECT COUNT(*) as count FROM units_test');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertEquals(0, $result['count'], 'Units table should be empty initially');
        
        $unit = new Unit()->setName('JohnUnit');
        $em->persist($unit);
        $createUser = new User()->setUsername('John')->setUnit($unit);
        $em->persist($createUser);
        $em->flush();
        
        // Verify what was actually created
        $stmt = $this->entityManager->getPdm()->prepare('SELECT COUNT(*) as count FROM units_test');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertEquals(1, $result['count'], 'Should have exactly 1 unit in database');
        
        $stmt = $this->entityManager->getPdm()->prepare('SELECT COUNT(*) as count FROM users_test');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertEquals(1, $result['count'], 'Should have exactly 1 user in database after creation');
        
        $user = $em->find(User::class, 'username=\'John\'');
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('John', $user->getUsername());
        self::assertEquals('JohnUnit', $user->getUnit()->getName());
        // The Id 2 is not used, there is only one user in the database
        $badUser = $em->find(User::class, 2);
        // Check if there is exactly one user in the database
        $stmt = $this->entityManager->getPdm()->prepare('SELECT COUNT(*) as count FROM users_test');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertEquals(1, $result['count'], 'Should still have exactly 1 user in database');
        self::assertNull($badUser);
    }

    public function testFindEntityWithOneToManyRelation(): void
    {
        $this->createGroupTestTable();
        $this->createUserTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        
        // Créer les entités avec des relations parent-enfant
        $group1 = new Group();
        $group1->setName('Group1');
        $group2 = new Group();
        $group2->setName('Group2');
        $group2->setParent($group1);
        $group3 = new Group();
        $group3->setName('Group3');
        $group3->setParent($group1);
        
        // Ne pas utiliser addChild() manuellement - laisser la persistance gérer les relations
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($group3);
        $em->flush();
        
        // Vérifier que les données sont correctement en base
        $pdo = $this->entityManager->getPdm();
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM groups_test WHERE parent_id = ?');
        $stmt->execute([$group1->getId()]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertEquals(2, $result['count'], 'Should have 2 children in database');
        
        // Clear l'entity manager pour forcer le rechargement depuis la base
        $em->getEmEngine()->clear();
        
        // Test 1: Vérifier que les relations OneToMany sont chargées automatiquement depuis la base
        $reloadedGroup1 = $em->find(Group::class, 'name=\'Group1\'');
        self::assertNotNull($reloadedGroup1, 'Group1 should be found after clear');
        self::assertInstanceOf(Group::class, $reloadedGroup1);
        self::assertEquals('Group1', $reloadedGroup1->getName());
        
        // IMPORTANT: Ce test devrait échouer si OneToManyProcessor::processProperty() n'est pas implémenté
        $children = $reloadedGroup1->getChildren();
        self::assertNotNull($children, 'Children collection should not be null');
        self::assertEquals(2, $children->count(), 'Group1 should have 2 children loaded from database');
        
        // Vérifier que les enfants sont les bonnes entités
        $childNames = [];
        foreach ($children as $child) {
            $childNames[] = $child->getName();
        }
        self::assertContains('Group2', $childNames, 'Group2 should be in children');
        self::assertContains('Group3', $childNames, 'Group3 should be in children');
        
        // Test 2: Vérifier la relation ManyToOne dans l'autre sens
        $reloadedGroup3 = $em->find(Group::class, 'name=\'Group3\'');
        self::assertNotNull($reloadedGroup3, 'Group3 should be found');
        self::assertEquals('Group1', $reloadedGroup3->getParent()->getName());
        
        // Test 3: Tester la modification des relations et la synchronisation
        $reloadedGroup3->setParent(null);
        
        // Check if entity manager detects the change
        $hasChanges = $em->getEmEngine()->hasChanges($reloadedGroup3);
        
        $em->persist($reloadedGroup3);
        $em->flush();
        
        // Vérifier en base que parent_id est null
        $stmt = $pdo->prepare('SELECT parent_id FROM groups_test WHERE name = ?');
        $stmt->execute(['Group3']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertNull($result['parent_id'], 'Group3 parent_id should be null in database');
        
        // Clear et recharger pour vérifier la synchronisation
        $em->getEmEngine()->clear();
        
        $finalGroup1 = $em->find(Group::class, 'name=\'Group1\'');
        $finalGroup3 = $em->find(Group::class, 'name=\'Group3\'');
        
        // Group1 ne devrait plus avoir que 1 enfant
        self::assertEquals(1, $finalGroup1->getChildren()->count(), 'Group1 should now have only 1 child');
        
        // Group3 ne devrait plus avoir de parent
        self::assertNull($finalGroup3->getParent(), 'Group3 should have no parent after update');
        
        // Test 4: Vérifier que l'enfant restant est Group2
        $remainingChild = $finalGroup1->getChildren()->reset();
        self::assertEquals('Group2', $remainingChild->getName(), 'Remaining child should be Group2');
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

        // Reload to get fresh state
        $reloadedUser2 = $em->find(User::class, 'username=\'User2\'');
        
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
            /** @var ChangeSet|null $update */
            $update = $event->getEntityChanges();
            if ($update === null) {
                return;
            }
            $username = $update->getFieldChange('username');
            if ($username === null || $username->oldValue === null || $username->newValue === null) {
                return;
            }
            $updateTag = implode('->', [$username->oldValue, $username->newValue]);
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
            if ($manager instanceof User && $manager->getUsername() === 'Manager') {
                $entityManager = $event->getEntityManager();
                $otherManager = $entityManager->find(User::class, 'username=\'OtherManager\'');
                $user = $entityManager->find(User::class, 'username=\'John\'');
                if ($user !== null && $otherManager !== null) {
                    $user->setManager($otherManager);
                    $entityManager->persist($user);
                    // Note: Don't flush here as we're already in a flush cycle
                }
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
            if ($manager instanceof User && $manager->getUsername() === 'Manager') {
                $em = $event->getEntityManager();
                $otherManager = $em->find(User::class, 'username=\'OtherManager\'');
                $user = $em->find(User::class, 'username=\'John\'');
                if ($user !== null && $otherManager !== null) {
                    $user->setManager($otherManager);
                    $em->persist($user);
                    $em->flush();
                }
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

    // Tests by Claude
    public function testByClaudeFindEntityWithOneToOneRelation(): void
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

        // Store John's ID to use a different one for the bad user test
        $johnId = $createUser->getId();

        $user = $em->find(User::class, 'username=\'John\'');
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('John', $user->getUsername());
        self::assertEquals('JohnUnit', $user->getUnit()->getName());

        // Use an ID that really doesn't exist (not John's ID)
        $nonExistentId = $johnId + 1000;
        $badUser = $em->find(User::class, $nonExistentId);
        self::assertNull($badUser);

        // Vérifier qu'il n'y a qu'un seul utilisateur
        $stmt = $this->entityManager->getPdm()->prepare('SELECT COUNT(*) as count FROM users_test');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertEquals(1, $result['count'], 'Should have exactly 1 user in database');
    }

    public function testByClaudeExecuteDeletionsAndPreRemoveEvent(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;

        // Create unit
        $unit = new Unit()->setName('TestUnit');
        $em->persist($unit);
        $em->flush();

        $manager = new User();
        $manager->setUsername('Manager');
        $manager->setUnit($unit);
        $otherManager = new User();
        $otherManager->setUsername('OtherManager');
        $otherManager->setUnit($unit);
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        $user->setManager($manager);
        $em->persist($manager);
        $em->persist($otherManager);
        $em->persist($user);
        $em->flush();

        // Store IDs for later use
        $managerId = $manager->getId();
        $otherManagerId = $otherManager->getId();
        $userId = $user->getId();

        // Register the event listener BEFORE the remove operation
        $eventFired = false;
        $this->eventManager->addListener(DbEvents::preRemove->value, static function (PreRemoveEvent $event) use ($em, $managerId, $otherManagerId, $userId, &$eventFired) {
            $entity = $event->getEntity();
            if ($entity instanceof User && $entity->getId() === $managerId) {
                $eventFired = true;
                // Update user's manager before the deletion actually happens
                $stmt = $em->getPdm()->prepare('UPDATE users_test SET manager = ? WHERE id = ?');
                $stmt->execute([$otherManagerId, $userId]);
            }
        });

        $em->remove($manager);
        $em->flush();

        self::assertTrue($eventFired, 'PreRemove event should have been fired');

        // Refresh from database to get updated data
        $user = $em->find(User::class, $userId);
        self::assertNotNull($user, 'User should still exist');
        self::assertNotNull($user->getManager(), 'User should have a manager');
        self::assertEquals('OtherManager', $user->getManager()->getUsername());
    }

    public function testByClaudeExecuteDeletionsAndPostRemoveEvent(): void
    {
        $this->createUserTestTable();
        $this->createGroupTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;

        // Create unit
        $unit = new Unit()->setName('TestUnit');
        $em->persist($unit);
        $em->flush();

        $manager = new User();
        $manager->setUsername('Manager');
        $manager->setUnit($unit);
        $otherManager = new User();
        $otherManager->setUsername('OtherManager');
        $otherManager->setUnit($unit);
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        $user->setManager($manager);
        $em->persist($manager);
        $em->persist($otherManager);
        $em->persist($user);
        $em->flush();

        // Store IDs for later use
        $managerId = $manager->getId();
        $otherManagerId = $otherManager->getId();
        $userId = $user->getId();

        // Register the event listener BEFORE the remove operation
        $eventFired = false;
        $this->eventManager->addListener(DbEvents::postRemove->value, function (PostRemoveEvent $event) use ($em, $managerId, $otherManagerId, $userId, &$eventFired) {
            $entity = $event->getEntity();
            if ($entity instanceof User && $entity->getId() === $managerId) {
                $eventFired = true;
                // Update user's manager after the deletion has happened
                $stmt = $em->getPdm()->prepare('UPDATE users_test SET manager = ? WHERE id = ?');
                $stmt->execute([$otherManagerId, $userId]);
            }
        });

        $em->remove($manager);
        $em->flush();

        self::assertTrue($eventFired, 'PostRemove event should have been fired');

        // Clear entity manager to force reload from database
        $em->getEmEngine()->clear();

        // Reload from database to get updated data
        $user = $em->find(User::class, $userId);
        self::assertNotNull($user, 'User should still exist');
        self::assertNotNull($user->getManager(), 'User should have a manager');
        self::assertNotNull($user->getUnit(), 'User should have a unit');
        self::assertEquals('OtherManager', $user->getManager()->getUsername());
    }

    /**
     * Test spécifique pour démontrer l'importance du OneToManyProcessor
     * Ce test devrait échouer si OneToManyProcessor::processProperty() n'est pas implémenté
     */
    public function testOneToManyProcessorIsRequired(): void
    {
        $this->createGroupTestTable();
        $this->createUserTestTable();
        $this->createLinkUserGroupTestTable();
        $em = $this->entityManager;
        
        // Créer un parent et ses enfants directement en base
        $pdo = $this->entityManager->getPdm();
        
        // Insérer le parent
        $stmt = $pdo->prepare('INSERT INTO groups_test (id, name) VALUES (?, ?)');
        $stmt->execute([1, 'ParentGroup']);
        
        // Insérer les enfants avec référence au parent
        $stmt = $pdo->prepare('INSERT INTO groups_test (id, name, parent_id) VALUES (?, ?, ?)');
        $stmt->execute([2, 'Child1', 1]);
        $stmt->execute([3, 'Child2', 1]);
        
        // Vérifier que les données sont bien en base
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM groups_test WHERE parent_id = 1');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertEquals(2, $result['count'], 'Should have 2 children in database');
        
        // Charger le parent via l'EntityManager
        $parentGroup = $em->find(Group::class, 1);
        self::assertNotNull($parentGroup, 'Parent group should be found');
        self::assertEquals('ParentGroup', $parentGroup->getName());
        
        // CRITICAL TEST: Vérifier que les enfants sont automatiquement chargés
        // Si OneToManyProcessor::processProperty() n'est pas implémenté, 
        // cette assertion devrait échouer car la collection sera vide
        $children = $parentGroup->getChildren();
        self::assertNotNull($children, 'Children collection should not be null');
        self::assertEquals(2, $children->count(), 
            'Parent should have 2 children loaded automatically by OneToManyProcessor. ' .
            'If this fails, OneToManyProcessor::processProperty() is not properly implemented.'
        );
        
        // Vérifier les noms des enfants
        $childNames = [];
        foreach ($children as $child) {
            $childNames[] = $child->getName();
        }
        self::assertCount(2, $childNames, 'Should have exactly 2 child names');
        self::assertContains('Child1', $childNames, 'Child1 should be loaded');
        self::assertContains('Child2', $childNames, 'Child2 should be loaded');
    }

    public function testDependencyManagerOrdering(): void
    {
        $dependencyManager = new \MulerTech\Database\ORM\State\DependencyManager();
        
        $unit1 = new Unit();
        $unit1->setName('Unit1');
        $unit2 = new Unit();
        $unit2->setName('Unit2');
        
        $user1 = new User();
        $user1->setUsername('User1');
        $user1->setUnit($unit1);
        
        $user2 = new User();
        $user2->setUsername('User2');
        $user2->setUnit($unit2);
        $user2->setManager($user1);

        $dependencyManager->addInsertionDependency($user2, $user1);
        $dependencyManager->addInsertionDependency($user2, $unit2);
        $dependencyManager->addInsertionDependency($user1, $unit1);

        $entities = [
            spl_object_id($user2) => $user2,
            spl_object_id($user1) => $user1,
            spl_object_id($unit2) => $unit2,
            spl_object_id($unit1) => $unit1,
        ];

        $ordered = $dependencyManager->orderByDependencies($entities);

        self::assertEquals(4, count($ordered), 'Should have 4 entities');

        $orderedIds = array_keys($ordered);
        $unit1Position = array_search(spl_object_id($unit1), $orderedIds, true);
        $unit2Position = array_search(spl_object_id($unit2), $orderedIds, true);
        $user1Position = array_search(spl_object_id($user1), $orderedIds, true);
        $user2Position = array_search(spl_object_id($user2), $orderedIds, true);

        self::assertNotFalse($unit1Position, 'Unit1 should be in ordered array');
        self::assertNotFalse($unit2Position, 'Unit2 should be in ordered array');
        self::assertNotFalse($user1Position, 'User1 should be in ordered array');
        self::assertNotFalse($user2Position, 'User2 should be in ordered array');

        self::assertLessThan($user1Position, $unit1Position, 'Unit1 should come before User1');
        self::assertLessThan($user2Position, $unit2Position, 'Unit2 should come before User2');
        self::assertLessThan($user2Position, $user1Position, 'User1 should come before User2');
    }
}

