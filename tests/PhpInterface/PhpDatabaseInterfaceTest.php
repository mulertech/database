<?php

namespace MulerTech\Database\Tests\PhpInterface;

use MulerTech\Database\Database\Interface\DatabaseParameterParser;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

class PhpDatabaseInterfaceTest extends TestCase
{
    private function getPhpDatabaseManager(): PhpDatabaseManager
    {
        return new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []);
    }

    private function getDbName(): string
    {
        return trim(getenv('DATABASE_PATH'), '/');
    }

    protected function setUp(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'DROP TABLE IF EXISTS test_table';
        $pdo->exec($query);
        $query = 'CREATE TABLE IF NOT EXISTS test_table (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, firstname VARCHAR(255), lastname VARCHAR(255))';
        $pdo->exec($query);
    }

    protected function tearDown(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'DROP TABLE IF EXISTS test_table';
        $pdo->exec($query);
    }

    public function testGetConnection(): void
    {
        $this->assertInstanceOf(
            PDO::class,
            (new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []))->getConnection()
        );
    }

    public function testPrepareStatement(): void
    {
        $query = 'SHOW DATABASES';
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare($query);
        self::assertEquals('SHOW DATABASES', $statement->getQueryString());
    }

    public function testBeginTransaction(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        self::assertTrue($pdo->beginTransaction());
    }

    public function testCommit(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        self::assertTrue($pdo->commit());
    }

    public function testRollback(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $pdo->beginTransaction();
        self::assertTrue($pdo->rollback());
    }

    public function testInTransaction(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        self::assertFalse($pdo->inTransaction());
        $pdo->beginTransaction();
        self::assertTrue($pdo->inTransaction());
    }

    public function testSetAndGetAttribute(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        self::assertFalse($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        self::assertTrue($pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false));
        self::assertEquals(0, $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }

    public function testExecLastInsertIdAndQuery(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES ("test")';
        $pdo->exec($query);
        self::assertEquals(1, $pdo->lastInsertId());
        self::assertEquals(
            1,
            $pdo->query('SELECT id FROM test_table WHERE firstname="test"')->fetch()['id']
        );
    }

    public function testErrorCodeAndErrorInfo(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        try {
            $pdo->exec('SELECT * FROM bones');
        } catch (PDOException) {
            self::assertEquals("Table '" . $this->getDbName() . ".bones' doesn't exist", $pdo->errorInfo()[2]);
            self::assertEquals('42S02', $pdo->errorCode());
        }
    }

    public function testQuote(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        self::assertEquals('\'test\'', $pdo->quote('test'));
    }

    public function testPopulateParameters(): void
    {
        $parameters[
            DatabaseParameterParser::DATABASE_URL
        ] = 'mysql://user:password@127.0.0.1:3306/db_name?serverVersion=5.7';
        $expected = [
            'scheme' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'user',
            'pass' => 'password',
            'path' => '/db_name',
            'query' => 'serverVersion=5.7',
            'dbname' => 'db_name',
            'serverVersion' => '5.7'
        ];
        self::assertEquals($expected, new DatabaseParameterParser()->parseParameters($parameters));
    }

    public function testPopulateParametersWithUrlDecode(): void
    {
        $parameters = [];
        $password = '@y[oy$R5i8';
        // urlencode($password) = %40y%5Boy%24R5i8;
        $parameters[
            DatabaseParameterParser::DATABASE_URL
        ] = 'mysql://db_user:%40y%5Boy%24R5i8@127.0.0.1:3306/db_name?serverVersion=5.7';
        $expected = [
            'scheme' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'db_user',
            'pass' => $password,
            'path' => '/db_name',
            'query' => 'serverVersion=5.7',
            'dbname' => 'db_name',
            'serverVersion' => '5.7'
        ];
        self::assertEquals($expected, new DatabaseParameterParser()->parseParameters($parameters));
    }

    public function testPopulateEnvParameters(): void
    {
        $scheme = getenv('DATABASE_SCHEME');
        $host = getenv('DATABASE_HOST');
        $port = getenv('DATABASE_PORT');
        $user = getenv('DATABASE_USER');
        $pass = getenv('DATABASE_PASS');
        $path = getenv('DATABASE_PATH');
        $query = getenv('DATABASE_QUERY');
        putenv('DATABASE_SCHEME=mysql');
        putenv('DATABASE_HOST=127.0.0.1');
        putenv('DATABASE_PORT=3306');
        putenv('DATABASE_USER=db_user');
        putenv('DATABASE_PASS=db_password');
        putenv('DATABASE_PATH=/db_name');
        putenv('DATABASE_QUERY=serverVersion=5.7');
        $expected = [
            'scheme' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'db_user',
            'pass' => 'db_password',
            'path' => '/db_name',
            'query' => 'serverVersion=5.7',
            'dbname' => 'db_name',
            'serverVersion' => '5.7'
        ];
        self::assertEquals($expected, new DatabaseParameterParser()->parseParameters());
        putenv("DATABASE_SCHEME=$scheme");
        putenv("DATABASE_HOST=$host");
        putenv("DATABASE_PORT=$port");
        putenv("DATABASE_USER=$user");
        putenv("DATABASE_PASS=$pass");
        putenv("DATABASE_PATH=$path");
        putenv("DATABASE_QUERY=$query");
    }

    public function testStatementExecute(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES ("test")';
        $pdo->exec($query);
        $statement = $pdo->prepare('SELECT id FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        self::assertEquals(1, $statement->fetch()['id']);
        $this->expectException(PDOException::class);
        $statement = $pdo->prepare('SELECT id FROM not_test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
    }

    public function testStatementFetch(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES ("test")';
        $pdo->exec($query);
        $statement = $pdo->prepare('SELECT id FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        self::assertEquals(1, $statement->fetch()['id']);
    }

    public function testStatementBindParam(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES (:firstname)';
        $statement = $pdo->prepare($query);
        $name = 'test';
        $statement->bindParam(':firstname', $name);
        $statement->execute();
        $statement = $pdo->prepare('SELECT id FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        self::assertEquals(1, $statement->fetch()['id']);
    }

    public function testStatementBindColumn(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES ("test")';
        $pdo->exec($query);
        $statement = $pdo->prepare('SELECT id, firstname FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        $statement->bindColumn(1, $id);
        $statement->bindColumn('firstname', $name);
        $statement->fetch();
        self::assertEquals(1, $id);
        self::assertEquals('test', $name);
    }

    public function testStatementBindValue(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES (:firstname)';
        $statement = $pdo->prepare($query);
        $name = 'test';
        $statement->bindValue(':firstname', $name, PDO::PARAM_STR);
        $statement->execute();
        $statement = $pdo->prepare('SELECT id FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        self::assertEquals(1, $statement->fetch()['id']);
    }

    public function testStatementRowCount(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES ("test")';
        $pdo->exec($query);
        $statement = $pdo->prepare('SELECT id FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        self::assertEquals(1, $statement->rowCount());
    }

    public function testStatementFetchColumn(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES ("test")';
        $pdo->exec($query);
        $statement = $pdo->prepare('SELECT id FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        self::assertEquals(1, $statement->fetchColumn());
    }

    public function testStatementFetchAll(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $query = 'INSERT INTO test_table (firstname) VALUES ("test")';
        $pdo->exec($query);
        $statement = $pdo->prepare('SELECT id FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        self::assertEquals([['id' => 1]], $statement->fetchAll(PDO::FETCH_DEFAULT));
        $statement->execute(['firstname' => 'test']);
        self::assertEquals([0 => 1], $statement->fetchAll(PDO::FETCH_COLUMN, 0));
        $statement->execute(['firstname' => 'test']);
        self::assertEquals([(object)['id' => 1]], $statement->fetchAll(PDO::FETCH_CLASS, 'stdClass'));
        $statement->execute(['firstname' => 'test']);
        self::assertEquals(
            [['the id is' => 1]],
            $statement->fetchAll(PDO::FETCH_FUNC, static fn($id) => ['the id is' => $id])
        );
    }

    public function testStatementFetchObject(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $pdo->exec('INSERT INTO test_table (firstname) VALUES ("test")');
        $statement = $pdo->prepare('SELECT id FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        self::assertEquals((object)['id' => 1], $statement->fetchObject());
    }

    public function testStatementErrorCodeAndErrorInfo(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Table '" . $this->getDbName() . ".bones' doesn't exist");
        $statement = $pdo->prepare('SELECT skull FROM bones');
        $statement->execute();
    }

    public function testStatementGetAttribute(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('SELECT id FROM test_table');
        self::assertIsBool($statement->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }

    public function testStatementColumnCount(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('SELECT firstname FROM test_table WHERE firstname="test"');
        self::assertEquals(0, $statement->columnCount());
        $statement->execute();
        self::assertEquals(1, $statement->columnCount());
        $statement = $pdo->prepare('SELECT firstname, lastname FROM test_table WHERE firstname="test"');
        self::assertEquals(0, $statement->columnCount());
        $statement->execute();
        self::assertEquals(2, $statement->columnCount());
    }

    public function testStatementGetColumnMeta(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('SELECT id, firstname, lastname FROM test_table');
        $statement->execute();
        self::assertEquals(
            [
                'native_type' => 'LONG',
                'flags' => [
                    0 => 'not_null',
                    1 => 'primary_key',
                ],
                'name' => 'id',
                'len' => 11,
                'precision' => 0,
                'pdo_type' => 1,
                'table' => 'test_table'
            ],
            $statement->getColumnMeta(0)
        );
    }

    public function testStatementGetIterator(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $pdo->exec('INSERT INTO test_table (firstname) VALUES ("test")');
        $statement = $pdo->prepare('SELECT id, firstname FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        foreach ($statement->getIterator() as $row) {
            self::assertEquals(1, $row['id']);
            self::assertEquals('test', $row['firstname']);
        }
    }

    public function testStatementSetFetchMode(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $pdo->exec('INSERT INTO test_table (firstname) VALUES ("test")');
        $statement = $pdo->prepare('SELECT id, firstname FROM test_table WHERE firstname=:firstname');
        $statement->execute(['firstname' => 'test']);
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        self::assertEquals(['id' => 1, 'firstname' => 'test'], $statement->fetch());
    }

    public function testStatementNextRowset(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $pdo->exec('INSERT INTO test_table (firstname) VALUES ("test"), ("test2")');
        $statement = $pdo->prepare('SELECT id, firstname FROM test_table');
        $statement->execute();
        self::assertEquals(
            [['id' => 1, 'firstname' => 'test'], ['id' => 2, 'firstname' => 'test2']],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
        self::assertFalse($statement->nextRowset());
    }

    public function testStatementCloseCursor(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $pdo->exec('INSERT INTO test_table (firstname) VALUES ("test"), ("test2")');
        $statement = $pdo->prepare('SELECT id, firstname FROM test_table');
        $statement->execute();
        self::assertEquals(
            [['id' => 1, 'firstname' => 'test'], ['id' => 2, 'firstname' => 'test2']],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
        $statement->closeCursor();
        self::assertEquals([], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testGetQueryString(): void
    {
        $pdo = $this->getPhpDatabaseManager();
        $statement = $pdo->prepare('SELECT id, firstname FROM test_table WHERE firstname=:firstname');
        self::assertEquals(
            'SELECT id, firstname FROM test_table WHERE firstname=:firstname',
            $statement->getQueryString()
        );
    }
}