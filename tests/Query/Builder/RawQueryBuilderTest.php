<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder;

use MulerTech\Database\Query\Builder\RawQueryBuilder;
use MulerTech\Database\ORM\EmEngine;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for RawQueryBuilder class
 */
class RawQueryBuilderTest extends TestCase
{
    public function testConstructor(): void
    {
        $sql = 'SELECT * FROM users';
        $builder = new RawQueryBuilder(null, $sql);
        
        $this->assertEquals($sql, $builder->toSql());
    }

    public function testConstructorWithEmEngine(): void
    {
        $emEngine = $this->createMock(EmEngine::class);
        $sql = 'SELECT * FROM users';
        $builder = new RawQueryBuilder($emEngine, $sql);
        
        $this->assertEquals($sql, $builder->toSql());
    }

    public function testBuildSql(): void
    {
        $sql = 'SELECT COUNT(*) FROM products';
        $builder = new RawQueryBuilder(null, $sql);
        
        $this->assertEquals($sql, $builder->toSql());
    }

    public function testGetQueryTypeSelect(): void
    {
        $builder = new RawQueryBuilder(null, 'SELECT * FROM users');
        $this->assertEquals('SELECT', $builder->getQueryType());
        
        $builder = new RawQueryBuilder(null, '  select * from users  ');
        $this->assertEquals('SELECT', $builder->getQueryType());
    }

    public function testGetQueryTypeInsert(): void
    {
        $builder = new RawQueryBuilder(null, 'INSERT INTO users (name) VALUES (?)');
        $this->assertEquals('INSERT', $builder->getQueryType());
        
        $builder = new RawQueryBuilder(null, '  insert into users values (1, "test")  ');
        $this->assertEquals('INSERT', $builder->getQueryType());
    }

    public function testGetQueryTypeUpdate(): void
    {
        $builder = new RawQueryBuilder(null, 'UPDATE users SET name = ?');
        $this->assertEquals('UPDATE', $builder->getQueryType());
        
        $builder = new RawQueryBuilder(null, '  update users set active = 1  ');
        $this->assertEquals('UPDATE', $builder->getQueryType());
    }

    public function testGetQueryTypeDelete(): void
    {
        $builder = new RawQueryBuilder(null, 'DELETE FROM users WHERE id = ?');
        $this->assertEquals('DELETE', $builder->getQueryType());
        
        $builder = new RawQueryBuilder(null, '  delete from users where active = 0  ');
        $this->assertEquals('DELETE', $builder->getQueryType());
    }

    public function testGetQueryTypeReplace(): void
    {
        $builder = new RawQueryBuilder(null, 'REPLACE INTO users (id, name) VALUES (1, ?)');
        $this->assertEquals('REPLACE', $builder->getQueryType());
        
        $builder = new RawQueryBuilder(null, '  replace into users values (1, "test")  ');
        $this->assertEquals('REPLACE', $builder->getQueryType());
    }

    public function testGetQueryTypeRaw(): void
    {
        $builder = new RawQueryBuilder(null, 'SHOW TABLES');
        $this->assertEquals('RAW', $builder->getQueryType());
        
        $builder = new RawQueryBuilder(null, 'DESCRIBE users');
        $this->assertEquals('RAW', $builder->getQueryType());
        
        $builder = new RawQueryBuilder(null, 'DROP TABLE temp_table');
        $this->assertEquals('RAW', $builder->getQueryType());
    }

    public function testSetSql(): void
    {
        $originalSql = 'SELECT * FROM users';
        $newSql = 'SELECT id, name FROM users WHERE active = 1';
        
        $builder = new RawQueryBuilder(null, $originalSql);
        $this->assertEquals($originalSql, $builder->toSql());
        
        $result = $builder->setSql($newSql);
        $this->assertSame($builder, $result); // Test fluent interface
        $this->assertEquals($newSql, $builder->toSql());
    }

    public function testAppendSql(): void
    {
        $originalSql = 'SELECT * FROM users';
        $appendSql = 'WHERE active = 1';
        $expectedSql = 'SELECT * FROM users WHERE active = 1';
        
        $builder = new RawQueryBuilder(null, $originalSql);
        $result = $builder->appendSql($appendSql);
        
        $this->assertSame($builder, $result); // Test fluent interface
        $this->assertEquals($expectedSql, $builder->toSql());
    }

    public function testAppendSqlMultiple(): void
    {
        $builder = new RawQueryBuilder(null, 'SELECT * FROM users');
        $builder->appendSql('WHERE active = 1')
               ->appendSql('ORDER BY name')
               ->appendSql('LIMIT 10');
        
        $expected = 'SELECT * FROM users WHERE active = 1 ORDER BY name LIMIT 10';
        $this->assertEquals($expected, $builder->toSql());
    }

    public function testBind(): void
    {
        $builder = new RawQueryBuilder(null, 'SELECT * FROM users WHERE id = ? AND name = ?');
        $bindings = ['value1' => 123, 'value2' => 'John'];
        
        $result = $builder->bind($bindings);
        $this->assertSame($builder, $result); // Test fluent interface
        
        // Check that values were added to parameter bag
        $parameterBag = $builder->getParameterBag();
        $this->assertCount(2, $parameterBag->toArray());
    }

    public function testIsSelect(): void
    {
        $builder = new RawQueryBuilder(null, 'SELECT * FROM users');
        $this->assertTrue($builder->isSelect());
        
        $builder = new RawQueryBuilder(null, 'INSERT INTO users VALUES (1)');
        $this->assertFalse($builder->isSelect());
    }

    public function testIsInsert(): void
    {
        $builder = new RawQueryBuilder(null, 'INSERT INTO users VALUES (1)');
        $this->assertTrue($builder->isInsert());
        
        $builder = new RawQueryBuilder(null, 'SELECT * FROM users');
        $this->assertFalse($builder->isInsert());
    }

    public function testIsUpdate(): void
    {
        $builder = new RawQueryBuilder(null, 'UPDATE users SET name = "test"');
        $this->assertTrue($builder->isUpdate());
        
        $builder = new RawQueryBuilder(null, 'SELECT * FROM users');
        $this->assertFalse($builder->isUpdate());
    }

    public function testIsDelete(): void
    {
        $builder = new RawQueryBuilder(null, 'DELETE FROM users WHERE id = 1');
        $this->assertTrue($builder->isDelete());
        
        $builder = new RawQueryBuilder(null, 'SELECT * FROM users');
        $this->assertFalse($builder->isDelete());
    }

    public function testEmptySql(): void
    {
        $builder = new RawQueryBuilder(null, '');
        $this->assertEquals('', $builder->toSql());
        $this->assertEquals('RAW', $builder->getQueryType());
    }

    public function testGetDebugInfo(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $builder = new RawQueryBuilder(null, $sql);
        $builder->bind(['userId' => 123]);
        
        $debugInfo = $builder->getDebugInfo();
        
        $this->assertIsArray($debugInfo);
        $this->assertEquals($sql, $debugInfo['sql']);
        $this->assertEquals('SELECT', $debugInfo['type']);
        $this->assertTrue($debugInfo['cached']);
        $this->assertIsArray($debugInfo['parameters']);
    }
}