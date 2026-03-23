<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\Statement;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(Statement::class)]
final class StatementTest extends TestCase
{
    private PDOStatement $mockPdoStatement;
    private Statement $statement;

    private function getMockPdoStatement(): PDOStatement
    {
        if (!isset($this->mockPdoStatement)) {
            $this->mockPdoStatement = $this->createMock(PDOStatement::class);
            $this->statement = new Statement($this->mockPdoStatement);
        }
        return $this->mockPdoStatement;
    }

    private function getStatement(): Statement
    {
        $this->getMockPdoStatement();
        return $this->statement;
    }

    public function testGetPdoStatement(): void
    {
        $pdoStatement = $this->createStub(PDOStatement::class);
        $statement = new Statement($pdoStatement);
        $this->assertSame($pdoStatement, $statement->getPdoStatement());
    }

    public function testExecuteWithoutParameters(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('execute')
            ->with(null)
            ->willReturn(true);

        $result = $this->getStatement()->execute();

        $this->assertTrue($result);
    }

    public function testExecuteWithParameters(): void
    {
        $params = ['id' => 1, 'name' => 'John'];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('execute')
            ->with($params)
            ->willReturn(true);

        $result = $this->getStatement()->execute($params);

        $this->assertTrue($result);
    }

    public function testExecuteThrowsExceptionOnFailure(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('execute')
            ->willReturn(false);

        $this->getMockPdoStatement()->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Syntax error']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Statement execution failed. Error: Syntax error');

        $this->getStatement()->execute();
    }

    public function testExecuteThrowsExceptionOnPdoException(): void
    {
        $pdoException = new PDOException('Database error', 1234);

        $this->getMockPdoStatement()->expects($this->once())
            ->method('execute')
            ->willThrowException($pdoException);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Database error');
        $this->expectExceptionCode(1234);

        $this->getStatement()->execute();
    }

    public function testFetch(): void
    {
        $expectedData = ['id' => 1, 'name' => 'John'];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_DEFAULT, PDO::FETCH_ORI_NEXT, 0)
            ->willReturn($expectedData);

        $result = $this->getStatement()->fetch();

        $this->assertEquals($expectedData, $result);
    }

    public function testFetchWithCustomParameters(): void
    {
        $expectedData = ['id' => 1, 'name' => 'John'];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC, PDO::FETCH_ORI_PRIOR, 5)
            ->willReturn($expectedData);

        $result = $this->getStatement()->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_PRIOR, 5);

        $this->assertEquals($expectedData, $result);
    }

    public function testBindParam(): void
    {
        $value = 'test_value';

        $this->getMockPdoStatement()->expects($this->once())
            ->method('bindParam')
            ->with(':param', $value, PDO::PARAM_STR, 0, null)
            ->willReturn(true);

        $result = $this->getStatement()->bindParam(':param', $value);

        $this->assertTrue($result);
    }

    public function testBindParamWithAllParameters(): void
    {
        $value = 123;

        $this->getMockPdoStatement()->expects($this->once())
            ->method('bindParam')
            ->with(1, $value, PDO::PARAM_INT, 10, 'driver_option')
            ->willReturn(true);

        $result = $this->getStatement()->bindParam(1, $value, PDO::PARAM_INT, 10, 'driver_option');

        $this->assertTrue($result);
    }

    public function testBindParamThrowsExceptionOnFailure(): void
    {
        $value = 'test';

        $this->getMockPdoStatement()->expects($this->once())
            ->method('bindParam')
            ->willReturn(false);

        $this->getMockPdoStatement()->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Bind parameter failed']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to bind parameter :param. Error: Bind parameter failed');

        $this->getStatement()->bindParam(':param', $value);
    }

    public function testBindColumn(): void
    {
        $variable = null;

        $this->getMockPdoStatement()->expects($this->once())
            ->method('bindColumn')
            ->with('column_name', $variable, PDO::PARAM_STR, 0, null)
            ->willReturn(true);

        $result = $this->getStatement()->bindColumn('column_name', $variable);

        $this->assertTrue($result);
    }

    public function testBindColumnThrowsExceptionOnFailure(): void
    {
        $variable = null;

        $this->getMockPdoStatement()->expects($this->once())
            ->method('bindColumn')
            ->willReturn(false);

        $this->getMockPdoStatement()->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Bind column failed']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to bind column column_name. Error: Bind column failed');

        $this->getStatement()->bindColumn('column_name', $variable);
    }

    public function testBindValue(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('bindValue')
            ->with(':param', 'value', PDO::PARAM_STR)
            ->willReturn(true);

        $result = $this->getStatement()->bindValue(':param', 'value');

        $this->assertTrue($result);
    }

    public function testBindValueWithType(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('bindValue')
            ->with(1, 123, PDO::PARAM_INT)
            ->willReturn(true);

        $result = $this->getStatement()->bindValue(1, 123, PDO::PARAM_INT);

        $this->assertTrue($result);
    }

    public function testBindValueThrowsExceptionOnFailure(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('bindValue')
            ->willReturn(false);

        $this->getMockPdoStatement()->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Bind value failed']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to bind value for parameter :param. Error: Bind value failed');

        $this->getStatement()->bindValue(':param', 'value');
    }

    public function testRowCount(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('rowCount')
            ->willReturn(5);

        $result = $this->getStatement()->rowCount();

        $this->assertEquals(5, $result);
    }

    public function testFetchColumn(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetchColumn')
            ->with(0)
            ->willReturn('column_value');

        $result = $this->getStatement()->fetchColumn();

        $this->assertEquals('column_value', $result);
    }

    public function testFetchColumnWithSpecificColumn(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetchColumn')
            ->with(2)
            ->willReturn('column_value');

        $result = $this->getStatement()->fetchColumn(2);

        $this->assertEquals('column_value', $result);
    }

    public function testFetchAll(): void
    {
        $expectedData = [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_DEFAULT)
            ->willReturn($expectedData);

        $result = $this->getStatement()->fetchAll();

        $this->assertEquals($expectedData, $result);
    }

    public function testFetchAllWithMode(): void
    {
        $expectedData = [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $result = $this->getStatement()->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals($expectedData, $result);
    }

    public function testFetchAllWithValidatedArgs(): void
    {
        $expectedData = [['id' => 1, 'name' => 'John']];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN, 0)
            ->willReturn($expectedData);

        $result = $this->getStatement()->fetchAll(PDO::FETCH_COLUMN, 0);

        $this->assertEquals($expectedData, $result);
    }

    public function testFetchObject(): void
    {
        $object = new stdClass();

        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetchObject')
            ->with('stdClass', [])
            ->willReturn($object);

        $result = $this->getStatement()->fetchObject();

        $this->assertSame($object, $result);
    }

    public function testFetchObjectWithCustomClass(): void
    {
        $object = new stdClass();
        $constructorArgs = ['arg1', 'arg2'];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('fetchObject')
            ->with('CustomClass', $constructorArgs)
            ->willReturn($object);

        $result = $this->getStatement()->fetchObject('CustomClass', $constructorArgs);

        $this->assertSame($object, $result);
    }

    public function testErrorCode(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('errorCode')
            ->willReturn('00000');

        $result = $this->getStatement()->errorCode();

        $this->assertEquals('00000', $result);
    }

    public function testErrorInfo(): void
    {
        $errorInfo = ['42000', 1064, 'Syntax error'];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('errorInfo')
            ->willReturn($errorInfo);

        $result = $this->getStatement()->errorInfo();

        $this->assertEquals($errorInfo, $result);
    }

    public function testSetAttribute(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('setAttribute')
            ->with(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL)
            ->willReturn(true);

        $result = $this->getStatement()->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL);

        $this->assertTrue($result);
    }

    public function testSetAttributeThrowsExceptionOnFailure(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('setAttribute')
            ->willReturn(false);

        $this->getMockPdoStatement()->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Set attribute failed']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to set attribute ' . PDO::ATTR_CURSOR . '. Error: Set attribute failed');

        $this->getStatement()->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL);
    }

    public function testGetAttribute(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('getAttribute')
            ->with(PDO::ATTR_CURSOR)
            ->willReturn(PDO::CURSOR_SCROLL);

        $result = $this->getStatement()->getAttribute(PDO::ATTR_CURSOR);

        $this->assertEquals(PDO::CURSOR_SCROLL, $result);
    }

    public function testColumnCount(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('columnCount')
            ->willReturn(3);

        $result = $this->getStatement()->columnCount();

        $this->assertEquals(3, $result);
    }

    public function testGetColumnMeta(): void
    {
        $meta = ['name' => 'id', 'table' => 'users', 'len' => 11];

        $this->getMockPdoStatement()->expects($this->once())
            ->method('getColumnMeta')
            ->with(0)
            ->willReturn($meta);

        $result = $this->getStatement()->getColumnMeta(0);

        $this->assertEquals($meta, $result);
    }

    public function testSetFetchMode(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('setFetchMode')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(true);

        $result = $this->getStatement()->setFetchMode(PDO::FETCH_ASSOC);

        $this->assertTrue($result);
    }

    public function testNextRowset(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('nextRowset')
            ->willReturn(true);

        $result = $this->getStatement()->nextRowset();

        $this->assertTrue($result);
    }

    public function testCloseCursor(): void
    {
        $this->getMockPdoStatement()->expects($this->once())
            ->method('closeCursor')
            ->willReturn(true);

        $result = $this->getStatement()->closeCursor();

        $this->assertTrue($result);
    }

    public function testDebugDumpParams(): void
    {
        // Mock output buffering for debugDumpParams
        $this->getMockPdoStatement()->expects($this->once())
            ->method('debugDumpParams');

        $result = $this->getStatement()->debugDumpParams();

        $this->assertIsString($result);
    }

    public function testGetIterator(): void
    {
        $pdoStatement = $this->createStub(PDOStatement::class);
        $statement = new Statement($pdoStatement);
        $iterator = $statement->getIterator();

        $this->assertSame($pdoStatement, $iterator);
    }

    public function testGetQueryString(): void
    {
        $pdoStatement = $this->createStub(PDOStatement::class);
        $pdoStatement->queryString = 'SELECT * FROM users';
        $statement = new Statement($pdoStatement);

        $result = $statement->getQueryString();

        $this->assertEquals('SELECT * FROM users', $result);
    }

    public function testStatementIsReadonly(): void
    {
        $reflection = new \ReflectionClass(Statement::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}