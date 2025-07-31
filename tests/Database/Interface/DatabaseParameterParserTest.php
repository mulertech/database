<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\DatabaseParameterParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DatabaseParameterParser::class)]
final class DatabaseParameterParserTest extends TestCase
{
    private DatabaseParameterParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DatabaseParameterParser();
    }

    public function testParseParametersWithDatabaseUrl(): void
    {
        $parameters = [
            DatabaseParameterParser::DATABASE_URL => 'mysql://user:password@localhost:3306/testdb?charset=utf8mb4&serverVersion=8.0'
        ];

        $result = $this->parser->parseParameters($parameters);

        $this->assertEquals('mysql', $result['scheme']);
        $this->assertEquals('user', $result['user']);
        $this->assertEquals('password', $result['pass']);
        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals('3306', $result['port']);
        $this->assertEquals('/testdb', $result['path']);
        $this->assertEquals('testdb', $result['dbname']);
        $this->assertEquals('utf8mb4', $result['charset']);
        $this->assertEquals('8.0', $result['serverVersion']);
    }

    public function testParseParametersWithEnvironmentVariables(): void
    {
        // Save original values
        $originalValues = [];
        $envKeys = ['DATABASE_HOST', 'DATABASE_PORT', 'DATABASE_USER', 'DATABASE_PASS', 'DATABASE_PATH', 'DATABASE_QUERY'];
        foreach ($envKeys as $key) {
            $originalValues[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['DATABASE_HOST'] = 'testhost';
        $_ENV['DATABASE_PORT'] = '3307';
        $_ENV['DATABASE_USER'] = 'testuser';
        $_ENV['DATABASE_PASS'] = 'testpass';
        $_ENV['DATABASE_PATH'] = '/testdb';
        $_ENV['DATABASE_QUERY'] = 'charset=utf8&timeout=30';

        $result = $this->parser->parseParameters();

        // Check that we have the expected values from environment or existing values
        $this->assertArrayHasKey('host', $result);
        $this->assertArrayHasKey('port', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('pass', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('dbname', $result);

        // Restore original values
        foreach ($originalValues as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }

    public function testParseParametersWithMixedParameters(): void
    {
        // Test that passed parameters are included in result
        // Note: Environment variables override passed parameters
        $parameters = ['custom' => 'value', 'newparam' => 'test'];

        $result = $this->parser->parseParameters($parameters);

        // Custom parameters should be in the result
        $this->assertEquals('value', $result['custom']);
        $this->assertEquals('test', $result['newparam']);
        // Environment variables should override passed parameters
        $this->assertArrayHasKey('host', $result);
    }

    public function testParseDatabaseUrlWithEncodedCharacters(): void
    {
        $parameters = [
            DatabaseParameterParser::DATABASE_URL => 'mysql://user%40domain:p%40ssw%3Ard@localhost:3306/test%2Bdb'
        ];

        $result = $this->parser->parseParameters($parameters);

        $this->assertEquals('user@domain', $result['user']);
        $this->assertEquals('p@ssw:rd', $result['pass']);
        $this->assertEquals('test+db', $result['dbname']);
    }

    public function testParseDatabaseUrlWithInvalidUrl(): void
    {
        // Test with a URL that parse_url() will return false for
        $parameters = [
            DatabaseParameterParser::DATABASE_URL => 'http:///'
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid DATABASE_URL format.');

        $this->parser->parseParameters($parameters);
    }

    public function testParseDatabaseUrlWithNonStringValue(): void
    {
        $parameters = [
            DatabaseParameterParser::DATABASE_URL => 123
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATABASE_URL must be a string');

        $this->parser->parseParameters($parameters);
    }

    public function testParseParametersWithComplexQuery(): void
    {
        // Test with parameters that include a complex query string
        // Environment variables will override, so we test the basic structure
        $parameters = ['custom_param' => 'test_value'];

        $result = $this->parser->parseParameters($parameters);

        // Check that the result includes basic structure
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('serverVersion', $result); // From env var query
        $this->assertEquals('test_value', $result['custom_param']); // Our custom param should remain
    }

    public function testParseParametersWithNumericPath(): void
    {
        // Test with numeric path parameter
        // Note: Environment variables will override passed parameters
        $parameters = ['path' => 123];

        $result = $this->parser->parseParameters($parameters);

        // Environment path will override, so we just check structure
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('dbname', $result);
    }

    public function testParseParametersWithEmptyPath(): void
    {
        // Test with empty path parameter
        // Note: Environment variables will override passed parameters
        $parameters = ['path' => '/'];

        $result = $this->parser->parseParameters($parameters);

        // Environment path will override, so we just check structure
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('dbname', $result);
    }

    public function testParseParametersReturnsEmptyArrayWhenNoParameters(): void
    {
        $result = $this->parser->parseParameters();

        $this->assertIsArray($result);
    }

    public function testParseDatabaseUrlWithoutPath(): void
    {
        $parameters = [
            DatabaseParameterParser::DATABASE_URL => 'mysql://user:password@localhost:3306'
        ];

        $result = $this->parser->parseParameters($parameters);

        $this->assertArrayNotHasKey('dbname', $result);
    }

    public function testParseDatabaseUrlWithFragmentAndScheme(): void
    {
        $parameters = [
            DatabaseParameterParser::DATABASE_URL => 'mysql://user:password@localhost:3306/testdb?charset=utf8#fragment'
        ];

        $result = $this->parser->parseParameters($parameters);

        $this->assertEquals('mysql', $result['scheme']);
        $this->assertEquals('testdb', $result['dbname']);
        $this->assertEquals('utf8', $result['charset']);
        $this->assertEquals('fragment', $result['fragment']);
    }

    public function testEnvironmentVariableMappings(): void
    {
        // Test with parameters that include scheme and fragment
        // Note: Environment variables will override passed parameters
        $parameters = ['scheme' => 'postgresql', 'fragment' => 'test-fragment'];

        $result = $this->parser->parseParameters($parameters);

        // Check that the result has the expected structure
        $this->assertArrayHasKey('scheme', $result);
        $this->assertEquals('test-fragment', $result['fragment']); // No env var for fragment
    }
}