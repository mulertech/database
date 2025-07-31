<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Types;

use MulerTech\Database\Mapping\Types\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnType::class)]
class ColumnTypeTest extends TestCase
{
    public function testColumnTypeIsStringBackedEnum(): void
    {
        $this->assertInstanceOf(\BackedEnum::class, ColumnType::INT);
        $this->assertIsString(ColumnType::INT->value);
    }

    public function testIntegerTypes(): void
    {
        $this->assertEquals('INT', ColumnType::INT->value);
        $this->assertEquals('TINYINT', ColumnType::TINYINT->value);
        $this->assertEquals('SMALLINT', ColumnType::SMALLINT->value);
        $this->assertEquals('MEDIUMINT', ColumnType::MEDIUMINT->value);
        $this->assertEquals('BIGINT', ColumnType::BIGINT->value);
    }

    public function testDecimalTypes(): void
    {
        $this->assertEquals('DECIMAL', ColumnType::DECIMAL->value);
        $this->assertEquals('FLOAT', ColumnType::FLOAT->value);
        $this->assertEquals('DOUBLE', ColumnType::DOUBLE->value);
    }

    public function testStringTypes(): void
    {
        $this->assertEquals('CHAR', ColumnType::CHAR->value);
        $this->assertEquals('VARCHAR', ColumnType::VARCHAR->value);
        $this->assertEquals('TEXT', ColumnType::TEXT->value);
        $this->assertEquals('TINYTEXT', ColumnType::TINYTEXT->value);
        $this->assertEquals('MEDIUMTEXT', ColumnType::MEDIUMTEXT->value);
        $this->assertEquals('LONGTEXT', ColumnType::LONGTEXT->value);
    }

    public function testBinaryTypes(): void
    {
        $this->assertEquals('BINARY', ColumnType::BINARY->value);
        $this->assertEquals('VARBINARY', ColumnType::VARBINARY->value);
        $this->assertEquals('BLOB', ColumnType::BLOB->value);
        $this->assertEquals('TINYBLOB', ColumnType::TINYBLOB->value);
        $this->assertEquals('MEDIUMBLOB', ColumnType::MEDIUMBLOB->value);
        $this->assertEquals('LONGBLOB', ColumnType::LONGBLOB->value);
    }

    public function testDateTimeTypes(): void
    {
        $this->assertEquals('DATE', ColumnType::DATE->value);
        $this->assertEquals('DATETIME', ColumnType::DATETIME->value);
        $this->assertEquals('TIMESTAMP', ColumnType::TIMESTAMP->value);
        $this->assertEquals('TIME', ColumnType::TIME->value);
        $this->assertEquals('YEAR', ColumnType::YEAR->value);
    }

    public function testSpecialTypes(): void
    {
        $this->assertEquals('ENUM', ColumnType::ENUM->value);
        $this->assertEquals('SET', ColumnType::SET->value);
        $this->assertEquals('JSON', ColumnType::JSON->value);
        $this->assertEquals('GEOMETRY', ColumnType::GEOMETRY->value);
        $this->assertEquals('POINT', ColumnType::POINT->value);
        $this->assertEquals('LINESTRING', ColumnType::LINESTRING->value);
        $this->assertEquals('POLYGON', ColumnType::POLYGON->value);
    }

    public function testCanBeUnsignedReturnsTrueForNumericTypes(): void
    {
        $unsignedTypes = [
            ColumnType::INT,
            ColumnType::TINYINT,
            ColumnType::SMALLINT,
            ColumnType::MEDIUMINT,
            ColumnType::BIGINT,
            ColumnType::DECIMAL,
            ColumnType::FLOAT,
            ColumnType::DOUBLE,
        ];

        foreach ($unsignedTypes as $type) {
            $this->assertTrue($type->canBeUnsigned(), "Type {$type->value} should be able to be unsigned");
        }
    }

    public function testCanBeUnsignedReturnsFalseForNonNumericTypes(): void
    {
        $nonUnsignedTypes = [
            ColumnType::VARCHAR,
            ColumnType::TEXT,
            ColumnType::DATE,
            ColumnType::DATETIME,
            ColumnType::ENUM,
            ColumnType::JSON,
            ColumnType::BLOB,
        ];

        foreach ($nonUnsignedTypes as $type) {
            $this->assertFalse($type->canBeUnsigned(), "Type {$type->value} should not be able to be unsigned");
        }
    }

    public function testIsTypeWithLengthReturnsTrueForVariableLengthTypes(): void
    {
        $lengthTypes = [
            ColumnType::CHAR,
            ColumnType::VARCHAR,
            ColumnType::BINARY,
            ColumnType::VARBINARY,
        ];

        foreach ($lengthTypes as $type) {
            $this->assertTrue($type->isTypeWithLength(), "Type {$type->value} should require length");
        }
    }

    public function testIsTypeWithLengthReturnsFalseForFixedTypes(): void
    {
        $nonLengthTypes = [
            ColumnType::TEXT,
            ColumnType::INT,
            ColumnType::DATE,
            ColumnType::ENUM,
            ColumnType::JSON,
        ];

        foreach ($nonLengthTypes as $type) {
            $this->assertFalse($type->isTypeWithLength(), "Type {$type->value} should not require length");
        }
    }

    public function testRequiresPrecisionReturnsTrueForDecimalTypes(): void
    {
        $precisionTypes = [
            ColumnType::DECIMAL,
            ColumnType::FLOAT,
            ColumnType::DOUBLE,
        ];

        foreach ($precisionTypes as $type) {
            $this->assertTrue($type->requiresPrecision(), "Type {$type->value} should require precision");
        }
    }

    public function testRequiresPrecisionReturnsFalseForNonDecimalTypes(): void
    {
        $nonPrecisionTypes = [
            ColumnType::INT,
            ColumnType::VARCHAR,
            ColumnType::TEXT,
            ColumnType::DATE,
            ColumnType::ENUM,
        ];

        foreach ($nonPrecisionTypes as $type) {
            $this->assertFalse($type->requiresPrecision(), "Type {$type->value} should not require precision");
        }
    }

    public function testRequiresChoicesReturnsTrueForEnumAndSetTypes(): void
    {
        $this->assertTrue(ColumnType::ENUM->requiresChoices());
        $this->assertTrue(ColumnType::SET->requiresChoices());
    }

    public function testRequiresChoicesReturnsFalseForNonEnumTypes(): void
    {
        $nonChoiceTypes = [
            ColumnType::INT,
            ColumnType::VARCHAR,
            ColumnType::TEXT,
            ColumnType::DATE,
            ColumnType::JSON,
        ];

        foreach ($nonChoiceTypes as $type) {
            $this->assertFalse($type->requiresChoices(), "Type {$type->value} should not require choices");
        }
    }

    public function testToSqlDefinitionForBasicTypes(): void
    {
        $this->assertEquals('INT', ColumnType::INT->toSqlDefinition());
        $this->assertEquals('VARCHAR', ColumnType::VARCHAR->toSqlDefinition());
        $this->assertEquals('TEXT', ColumnType::TEXT->toSqlDefinition());
        $this->assertEquals('DATE', ColumnType::DATE->toSqlDefinition());
    }

    public function testToSqlDefinitionWithLength(): void
    {
        $this->assertEquals('VARCHAR(255)', ColumnType::VARCHAR->toSqlDefinition(length: 255));
        $this->assertEquals('CHAR(10)', ColumnType::CHAR->toSqlDefinition(length: 10));
        $this->assertEquals('BINARY(16)', ColumnType::BINARY->toSqlDefinition(length: 16));
        $this->assertEquals('VARBINARY(255)', ColumnType::VARBINARY->toSqlDefinition(length: 255));
    }

    public function testToSqlDefinitionWithPrecisionAndScale(): void
    {
        $this->assertEquals('DECIMAL(10,2)', ColumnType::DECIMAL->toSqlDefinition(length: 10, scale: 2));
        $this->assertEquals('FLOAT(7,4)', ColumnType::FLOAT->toSqlDefinition(length: 7, scale: 4));
        $this->assertEquals('DOUBLE(15,5)', ColumnType::DOUBLE->toSqlDefinition(length: 15, scale: 5));
    }

    public function testToSqlDefinitionWithPrecisionOnly(): void
    {
        $this->assertEquals('DECIMAL(10,0)', ColumnType::DECIMAL->toSqlDefinition(length: 10));
        $this->assertEquals('FLOAT(7,0)', ColumnType::FLOAT->toSqlDefinition(length: 7));
    }

    public function testToSqlDefinitionWithUnsigned(): void
    {
        $this->assertEquals('INT unsigned', ColumnType::INT->toSqlDefinition(isUnsigned: true));
        $this->assertEquals('BIGINT unsigned', ColumnType::BIGINT->toSqlDefinition(isUnsigned: true));
        $this->assertEquals('DECIMAL(10,2) unsigned', ColumnType::DECIMAL->toSqlDefinition(length: 10, scale: 2, isUnsigned: true));
    }

    public function testToSqlDefinitionWithChoices(): void
    {
        $choices = ['active', 'inactive', 'pending'];
        $this->assertEquals("ENUM('active','inactive','pending')", ColumnType::ENUM->toSqlDefinition(choices: $choices));
        
        $setChoices = ['read', 'write', 'execute'];
        $this->assertEquals("SET('read','write','execute')", ColumnType::SET->toSqlDefinition(choices: $setChoices));
    }

    public function testToSqlDefinitionIgnoresUnsignedForNonNumericTypes(): void
    {
        $this->assertEquals('VARCHAR(255)', ColumnType::VARCHAR->toSqlDefinition(length: 255, isUnsigned: true));
        $this->assertEquals('TEXT', ColumnType::TEXT->toSqlDefinition(isUnsigned: true));
        $this->assertEquals('DATE', ColumnType::DATE->toSqlDefinition(isUnsigned: true));
    }

    public function testToSqlDefinitionIgnoresLengthForNonLengthTypes(): void
    {
        $this->assertEquals('TEXT', ColumnType::TEXT->toSqlDefinition(length: 255));
        $this->assertEquals('INT', ColumnType::INT->toSqlDefinition(length: 11));
        $this->assertEquals('DATE', ColumnType::DATE->toSqlDefinition(length: 10));
    }

    public function testFromStringValues(): void
    {
        $this->assertEquals(ColumnType::INT, ColumnType::from('INT'));
        $this->assertEquals(ColumnType::VARCHAR, ColumnType::from('VARCHAR'));
        $this->assertEquals(ColumnType::DECIMAL, ColumnType::from('DECIMAL'));
        $this->assertEquals(ColumnType::ENUM, ColumnType::from('ENUM'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(ColumnType::tryFrom('INVALID_TYPE'));
        $this->assertNull(ColumnType::tryFrom('STRING'));
        $this->assertNull(ColumnType::tryFrom('NUMBER'));
    }

    public function testGetAllCases(): void
    {
        $cases = ColumnType::cases();
        $this->assertGreaterThan(20, count($cases));
        $this->assertContains(ColumnType::INT, $cases);
        $this->assertContains(ColumnType::VARCHAR, $cases);
        $this->assertContains(ColumnType::DECIMAL, $cases);
        $this->assertContains(ColumnType::JSON, $cases);
    }
}