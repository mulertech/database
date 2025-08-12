<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder;

use MulerTech\Database\Query\Builder\Raw;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for Raw class
 */
class RawTest extends TestCase
{
    public function testConstructor(): void
    {
        $rawValue = 'NOW()';
        $raw = new Raw($rawValue);
        
        $this->assertEquals($rawValue, $raw->getValue());
    }

    public function testStaticValue(): void
    {
        $rawValue = 'COUNT(*)';
        $raw = Raw::value($rawValue);
        
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertEquals($rawValue, $raw->getValue());
    }

    public function testGetValue(): void
    {
        $rawValue = 'MAX(created_at)';
        $raw = new Raw($rawValue);
        
        $this->assertEquals($rawValue, $raw->getValue());
    }

    public function testToString(): void
    {
        $rawValue = 'CURRENT_TIMESTAMP';
        $raw = new Raw($rawValue);
        
        $this->assertEquals($rawValue, (string) $raw);
        $this->assertEquals($rawValue, $raw->__toString());
    }

    public function testEmptyValue(): void
    {
        $raw = new Raw('');
        
        $this->assertEquals('', $raw->getValue());
        $this->assertEquals('', (string) $raw);
    }

    public function testComplexSqlValue(): void
    {
        $rawValue = 'CASE WHEN age > 18 THEN "adult" ELSE "minor" END';
        $raw = new Raw($rawValue);
        
        $this->assertEquals($rawValue, $raw->getValue());
        $this->assertEquals($rawValue, (string) $raw);
    }
}