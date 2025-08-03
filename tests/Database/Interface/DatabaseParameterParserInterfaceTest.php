<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\DatabaseParameterParserInterface;
use PHPUnit\Framework\TestCase;

class DatabaseParameterParserInterfaceTest extends TestCase
{
    private DatabaseParameterParserInterface $parser;

    protected function setUp(): void
    {
        $this->parser = new class implements DatabaseParameterParserInterface {
            public function parseParameters(array $parameters = []): array
            {
                return array_merge([
                    'host' => 'localhost',
                    'port' => 3306,
                    'charset' => 'utf8mb4'
                ], $parameters);
            }
        };
    }

    public function testParseParametersWithDefaults(): void
    {
        $result = $this->parser->parseParameters();
        
        $this->assertIsArray($result);
        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals(3306, $result['port']);
        $this->assertEquals('utf8mb4', $result['charset']);
    }

    public function testParseParametersWithCustomValues(): void
    {
        $params = [
            'host' => 'custom-host',
            'port' => 5432,
            'dbname' => 'test_db'
        ];
        
        $result = $this->parser->parseParameters($params);
        
        $this->assertEquals('custom-host', $result['host']);
        $this->assertEquals(5432, $result['port']);
        $this->assertEquals('test_db', $result['dbname']);
        $this->assertEquals('utf8mb4', $result['charset']);
    }

    public function testParseParametersOverridesDefaults(): void
    {
        $params = [
            'host' => 'override-host',
            'charset' => 'latin1'
        ];
        
        $result = $this->parser->parseParameters($params);
        
        $this->assertEquals('override-host', $result['host']);
        $this->assertEquals('latin1', $result['charset']);
        $this->assertEquals(3306, $result['port']);
    }

    public function testParseParametersWithEmptyArray(): void
    {
        $result = $this->parser->parseParameters([]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('host', $result);
        $this->assertArrayHasKey('port', $result);
        $this->assertArrayHasKey('charset', $result);
    }

    public function testParseParametersReturnsArray(): void
    {
        $result = $this->parser->parseParameters(['custom' => 'value']);
        
        $this->assertIsArray($result);
        $this->assertEquals('value', $result['custom']);
    }
}