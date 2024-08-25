<?php

namespace MulerTech\Database\Tests;

use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\NonRelational\DocumentStore\FileContent\AttributeReader;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\Database\Relational\Sql\SqlQuery;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Repository\UserRepository;
use MulerTech\EventManager\EventManager;
use MulerTech\EventManager\EventManagerInterface;
use MulerTech\EventManager\Tests\FakeClass\PersonEvent;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function getEntityManager(): EntityManagerInterface
    {
        $this->eventManager = new EventManager();
        return new EntityManager(
            $this->getPhpDatabaseManager(),
            new DbMapping(new AttributeReader(), __DIR__ . '/Files/Entity'),
            $this->eventManager
        );
    }

    public function testGetRepository(): void
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository(User::class);
        self::assertInstanceOf(UserRepository::class, $repository);
    }

    public function testExecuteInsertions(): void
    {
        $this->createTestTable();
        $em = $this->getEntityManager();
        $user = new User();
        $user->setUsername('John');
        $em->persist($user);
        $em->flush();
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('SELECT * FROM users_test');
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        self::assertEquals(['id' => 1, 'username' => 'John', 'unit_id' => null], $statement->fetch());
    }

    public function testEvent(): void
    {
        //todo: test event
//        $this->eventManager->addListener('person.event', static function (PersonEvent $event) {
//            $event->getTarget()->setTest('hello ' . $event->getTarget()->getName());
//        });
    }
}