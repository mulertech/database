<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\DatabaseUtilities;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseUtilities::class)]
final class DatabaseUtilitiesTest extends TestCase
{
    private PDO $mockConnection;
    private DatabaseUtilities $utilities;

    protected function setUp(): void
    {
        $this->mockConnection = $this->createMock(PDO::class);
        $this->utilities = new DatabaseUtilities($this->mockConnection);
    }

    public function testErrorCode(): void
    {
        $this->mockConnection->expects($this->once())
            ->method('errorCode')
            ->willReturn('00000');

        $result = $this->utilities->errorCode();

        $this->assertEquals('00000', $result);
    }

    public function testErrorCodeWhenNull(): void
    {
        $this->mockConnection->expects($this->once())
            ->method('errorCode')
            ->willReturn(null);

        $result = $this->utilities->errorCode();

        $this->assertFalse($result);
    }

    public function testErrorInfo(): void
    {
        $expectedErrorInfo = ['00000', null, null];
        
        $this->mockConnection->expects($this->once())
            ->method('errorInfo')
            ->willReturn($expectedErrorInfo);

        $result = $this->utilities->errorInfo();

        $this->assertEquals($expectedErrorInfo, $result);
    }

    public function testQuoteWithDefaultType(): void
    {
        $testString = "test'string";
        $expectedQuoted = "'test\\'string'";

        $this->mockConnection->expects($this->once())
            ->method('quote')
            ->with($testString, PDO::PARAM_STR)
            ->willReturn($expectedQuoted);

        $result = $this->utilities->quote($testString);

        $this->assertEquals($expectedQuoted, $result);
    }

    public function testQuoteWithSpecificType(): void
    {
        $testString = "123";
        $expectedQuoted = "123";

        $this->mockConnection->expects($this->once())
            ->method('quote')
            ->with($testString, PDO::PARAM_INT)
            ->willReturn($expectedQuoted);

        $result = $this->utilities->quote($testString, PDO::PARAM_INT);

        $this->assertEquals($expectedQuoted, $result);
    }

    public function testGetAttribute(): void
    {
        $attribute = PDO::ATTR_SERVER_VERSION;
        $expectedValue = '8.0.25';

        $this->mockConnection->expects($this->once())
            ->method('getAttribute')
            ->with($attribute)
            ->willReturn($expectedValue);

        $result = $this->utilities->getAttribute($attribute);

        $this->assertEquals($expectedValue, $result);
    }

    public function testGetAttributeWithDifferentTypes(): void
    {
        $this->mockConnection->expects($this->exactly(3))
            ->method('getAttribute')
            ->willReturnOnConsecutiveCalls(
                'string_value',
                123,
                true
            );

        $stringResult = $this->utilities->getAttribute(PDO::ATTR_SERVER_VERSION);
        $intResult = $this->utilities->getAttribute(PDO::ATTR_TIMEOUT);
        $boolResult = $this->utilities->getAttribute(PDO::ATTR_PERSISTENT);

        $this->assertEquals('string_value', $stringResult);
        $this->assertEquals(123, $intResult);
        $this->assertTrue($boolResult);
    }

    public function testErrorInfoWithActualError(): void
    {
        $errorInfo = ['42S02', 1146, "Table 'test.nonexistent' doesn't exist"];

        $this->mockConnection->expects($this->once())
            ->method('errorInfo')
            ->willReturn($errorInfo);

        $result = $this->utilities->errorInfo();

        $this->assertEquals('42S02', $result[0]);
        $this->assertEquals(1146, $result[1]);
        $this->assertEquals("Table 'test.nonexistent' doesn't exist", $result[2]);
    }

    public function testQuoteWithSpecialCharacters(): void
    {
        $testString = "test\nstring\twith\r\nspecial\0chars";
        $expectedQuoted = "'test\\nstring\\twith\\r\\nspecial\\0chars'";

        $this->mockConnection->expects($this->once())
            ->method('quote')
            ->with($testString, PDO::PARAM_STR)
            ->willReturn($expectedQuoted);

        $result = $this->utilities->quote($testString);

        $this->assertEquals($expectedQuoted, $result);
    }

    public function testQuoteWithEmptyString(): void
    {
        $testString = "";
        $expectedQuoted = "''";

        $this->mockConnection->expects($this->once())
            ->method('quote')
            ->with($testString, PDO::PARAM_STR)
            ->willReturn($expectedQuoted);

        $result = $this->utilities->quote($testString);

        $this->assertEquals($expectedQuoted, $result);
    }
}