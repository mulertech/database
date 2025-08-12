<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder;

use MulerTech\Database\Query\Builder\AbstractQueryBuilder;
use MulerTech\Database\Query\Builder\Raw;
use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\Interface\Statement;
use MulerTech\Database\Tests\Files\Query\Builder\TestableQueryBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use PDO;

/**
 * Test cases for AbstractQueryBuilder class
 */
class AbstractQueryBuilderTest extends TestCase
{
    private TestableQueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TestableQueryBuilder();
    }

    public function testConstructor(): void
    {
        $builder = new TestableQueryBuilder();
        $this->assertInstanceOf(AbstractQueryBuilder::class, $builder);
        $this->assertInstanceOf(QueryParameterBag::class, $builder->getParameterBag());
    }

    public function testConstructorWithEmEngine(): void
    {
        $emEngine = $this->createMock(EmEngine::class);
        $builder = new TestableQueryBuilder($emEngine);
        $this->assertInstanceOf(AbstractQueryBuilder::class, $builder);
    }

    public function testToSql(): void
    {
        $expectedSql = 'SELECT * FROM test';
        $this->builder->setSql($expectedSql);
        
        $this->assertEquals($expectedSql, $this->builder->toSql());
    }

    public function testGetResultWithoutEmEngine(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EmEngine is not set. Cannot prepare statement.');
        
        $this->builder->getResult();
    }

    public function testGetResultWithEmEngine(): void
    {
        $stmt = $this->createMock(Statement::class);
        $pdm = $this->createMock(PhpDatabaseManager::class);
        $pdm->expects($this->once())
           ->method('prepare')
           ->willReturn($stmt);
           
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->expects($this->once())
                     ->method('getPdm')
                     ->willReturn($pdm);
                     
        $emEngine = $this->createMock(EmEngine::class);
        $emEngine->expects($this->once())
                ->method('getEntityManager')
                ->willReturn($entityManager);
        
        $builder = new TestableQueryBuilder($emEngine);
        $builder->setSql('SELECT * FROM test');
        
        $result = $builder->getResult();
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecute(): void
    {
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->expects($this->once())->method('rowCount')->willReturn(5);
        
        $builder = $this->getMockBuilder(TestableQueryBuilder::class)
                       ->onlyMethods(['getResult'])
                       ->getMock();
        $builder->expects($this->once())->method('getResult')->willReturn($stmt);
        
        $result = $builder->execute();
        $this->assertEquals(5, $result);
    }

    public function testFetchAllWithStdClass(): void
    {
        $obj1 = new stdClass();
        $obj1->id = 1;
        $obj2 = new stdClass();
        $obj2->id = 2;
        $fetchResults = [$obj1, $obj2];
        
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->expects($this->once())
             ->method('fetchAll')
             ->with(PDO::FETCH_OBJ)
             ->willReturn($fetchResults);
        
        $builder = $this->getMockBuilder(TestableQueryBuilder::class)
                       ->onlyMethods(['getResult'])
                       ->getMock();
        $builder->expects($this->once())->method('getResult')->willReturn($stmt);
        
        $result = $builder->fetchAll();
        $this->assertEquals($fetchResults, $result);
    }

    public function testFetchAllWithCustomClass(): void
    {
        $fetchResults = [new stdClass(), new stdClass()];
        $customClass = 'SomeCustomClass';
        
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->expects($this->once())
             ->method('fetchAll')
             ->with(PDO::FETCH_CLASS, $customClass)
             ->willReturn($fetchResults);
        
        $builder = $this->getMockBuilder(TestableQueryBuilder::class)
                       ->onlyMethods(['getResult'])
                       ->getMock();
        $builder->expects($this->once())->method('getResult')->willReturn($stmt);
        
        $result = $builder->fetchAll($customClass);
        $this->assertEquals($fetchResults, $result);
    }

    public function testFetchOneWithStdClass(): void
    {
        $obj = new stdClass();
        $obj->id = 1;
        
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->expects($this->once())
             ->method('fetch')
             ->with(PDO::FETCH_OBJ)
             ->willReturn($obj);
        
        $builder = $this->getMockBuilder(TestableQueryBuilder::class)
                       ->onlyMethods(['getResult'])
                       ->getMock();
        $builder->expects($this->once())->method('getResult')->willReturn($stmt);
        
        $result = $builder->fetchOne();
        $this->assertEquals($obj, $result);
    }

    public function testFetchOneWithCustomClass(): void
    {
        $obj = new stdClass();
        $customClass = 'SomeCustomClass';
        
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->expects($this->once())->method('setFetchMode')->with(PDO::FETCH_CLASS, $customClass);
        $stmt->expects($this->once())->method('fetch')->willReturn($obj);
        
        $builder = $this->getMockBuilder(TestableQueryBuilder::class)
                       ->onlyMethods(['getResult'])
                       ->getMock();
        $builder->expects($this->once())->method('getResult')->willReturn($stmt);
        
        $result = $builder->fetchOne($customClass);
        $this->assertEquals($obj, $result);
    }

    public function testFetchOneReturnsNull(): void
    {
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->expects($this->once())
             ->method('fetch')
             ->with(PDO::FETCH_OBJ)
             ->willReturn(false);
        
        $builder = $this->getMockBuilder(TestableQueryBuilder::class)
                       ->onlyMethods(['getResult'])
                       ->getMock();
        $builder->expects($this->once())->method('getResult')->willReturn($stmt);
        
        $result = $builder->fetchOne();
        $this->assertNull($result);
    }

    public function testFetchScalar(): void
    {
        $scalarValue = 42;
        
        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->expects($this->once())->method('fetchColumn')->willReturn($scalarValue);
        
        $builder = $this->getMockBuilder(TestableQueryBuilder::class)
                       ->onlyMethods(['getResult'])
                       ->getMock();
        $builder->expects($this->once())->method('getResult')->willReturn($stmt);
        
        $result = $builder->fetchScalar();
        $this->assertEquals($scalarValue, $result);
    }

    public function testGetParameterBag(): void
    {
        $parameterBag = $this->builder->getParameterBag();
        $this->assertInstanceOf(QueryParameterBag::class, $parameterBag);
    }

    public function testClone(): void
    {
        $this->builder->getParameterBag()->add('test', PDO::PARAM_STR);
        
        $clone = $this->builder->clone();
        
        $this->assertNotSame($this->builder, $clone);
        $this->assertNotSame($this->builder->getParameterBag(), $clone->getParameterBag());
    }

    public function testGetDebugInfo(): void
    {
        $this->builder->setSql('SELECT * FROM test');
        $this->builder->getParameterBag()->add('test', PDO::PARAM_STR);
        
        $debugInfo = $this->builder->getDebugInfo();
        
        $this->assertIsArray($debugInfo);
        $this->assertArrayHasKey('sql', $debugInfo);
        $this->assertArrayHasKey('parameters', $debugInfo);
        $this->assertArrayHasKey('type', $debugInfo);
        $this->assertArrayHasKey('cached', $debugInfo);
        
        $this->assertEquals('SELECT * FROM test', $debugInfo['sql']);
        $this->assertEquals('TEST', $debugInfo['type']);
    }

    public function testBindParameterWithRaw(): void
    {
        $raw = new Raw('NOW()');
        $result = $this->builder->testBindParameter($raw);
        
        $this->assertEquals('NOW()', $result);
    }

    public function testBindParameterWithValue(): void
    {
        $result = $this->builder->testBindParameter('test');
        
        $this->assertStringStartsWith(':', $result); // Should be a parameter placeholder
    }

    public function testValidateTableName(): void
    {
        $this->builder->testValidateTableName('valid_table');
        $this->builder->testValidateTableName('users');
        $this->builder->testValidateTableName('table123');
        
        $this->addToAssertionCount(3); // No exceptions thrown
    }

    public function testValidateTableNameEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Table name cannot be empty');
        
        $this->builder->testValidateTableName('');
    }

    public function testValidateTableNameInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name format');
        
        $this->builder->testValidateTableName('invalid-table');
    }

    public function testValidateColumnName(): void
    {
        $this->builder->testValidateColumnName('valid_column');
        $this->builder->testValidateColumnName('name');
        $this->builder->testValidateColumnName('column123');
        
        $this->addToAssertionCount(3); // No exceptions thrown
    }

    public function testValidateColumnNameEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column name cannot be empty');
        
        $this->builder->testValidateColumnName('');
    }

    public function testValidateColumnNameInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid column name format');
        
        $this->builder->testValidateColumnName('invalid-column');
    }

    public function testBuildSetClause(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $result = $this->builder->testBuildSetClause($data);
        
        $this->assertStringContainsString('`name` =', $result);
        $this->assertStringContainsString('`age` =', $result);
        $this->assertStringContainsString(',', $result);
    }

    public function testBuildSetClauseWithRaw(): void
    {
        $data = ['name' => 'John', 'updated_at' => new Raw('NOW()')];
        $result = $this->builder->testBuildSetClause($data);
        
        $this->assertStringContainsString('`name` =', $result);
        $this->assertStringContainsString('`updated_at` = NOW()', $result);
    }
}