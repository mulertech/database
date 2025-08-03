<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Parameters;

use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\Database\Interface\Statement;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryParameterBag::class)]
final class QueryParameterBagTest extends TestCase
{
    private QueryParameterBag $bag;

    protected function setUp(): void
    {
        $this->bag = new QueryParameterBag();
    }

    public function testConstructor(): void
    {
        $bag = new QueryParameterBag();
        
        $this->assertTrue($bag->isEmpty());
        $this->assertEquals(0, $bag->count());
    }

    public function testAddWithAutoTypeDetection(): void
    {
        $placeholder = $this->bag->add('test value');
        
        $this->assertEquals(':param0', $placeholder);
        $this->assertEquals(1, $this->bag->count());
        $this->assertFalse($this->bag->isEmpty());
        
        $values = $this->bag->getNamedValues();
        $this->assertEquals(['test value'], array_values($values));
        
        $params = $this->bag->toArray();
        $this->assertEquals([
            ':param0' => ['value' => 'test value', 'type' => PDO::PARAM_STR]
        ], $params);
    }

    public function testAddWithExplicitType(): void
    {
        $placeholder = $this->bag->add(123, PDO::PARAM_INT);
        
        $this->assertEquals(':param0', $placeholder);
        
        $params = $this->bag->toArray();
        $this->assertEquals([
            ':param0' => ['value' => 123, 'type' => PDO::PARAM_INT]
        ], $params);
    }

    public function testAddMultipleParameters(): void
    {
        $placeholder1 = $this->bag->add('first');
        $placeholder2 = $this->bag->add(42);
        $placeholder3 = $this->bag->add(true);
        
        $this->assertEquals(':param0', $placeholder1);
        $this->assertEquals(':param1', $placeholder2);
        $this->assertEquals(':param2', $placeholder3);
        $this->assertEquals(3, $this->bag->count());
        
        $values = $this->bag->getNamedValues();
        $expected = [
            ':param0' => 'first',
            ':param1' => 42,
            ':param2' => true
        ];
        $this->assertEquals($expected, $values);
    }

    public function testAddNamedWithoutColon(): void
    {
        $placeholder = $this->bag->addNamed('user_id', 123);
        
        $this->assertEquals(':user_id', $placeholder);
        $this->assertEquals(1, $this->bag->count());
        
        $params = $this->bag->toArray();
        $this->assertEquals([
            ':user_id' => ['value' => 123, 'type' => PDO::PARAM_INT]
        ], $params);
    }

    public function testAddNamedWithColon(): void
    {
        $placeholder = $this->bag->addNamed(':user_name', 'John Doe');
        
        $this->assertEquals(':user_name', $placeholder);
        
        $params = $this->bag->toArray();
        $this->assertEquals([
            ':user_name' => ['value' => 'John Doe', 'type' => PDO::PARAM_STR]
        ], $params);
    }

    public function testAddNamedWithExplicitType(): void
    {
        $placeholder = $this->bag->addNamed('status', '1', PDO::PARAM_INT);
        
        $this->assertEquals(':status', $placeholder);
        
        $params = $this->bag->toArray();
        $this->assertEquals([
            ':status' => ['value' => '1', 'type' => PDO::PARAM_INT]
        ], $params);
    }

    public function testAddNamedOverwrites(): void
    {
        $this->bag->addNamed('param', 'first value');
        $this->bag->addNamed('param', 'second value');
        
        $this->assertEquals(1, $this->bag->count());
        
        $values = $this->bag->getNamedValues();
        $this->assertEquals([':param' => 'second value'], $values);
    }

    public function testTypeDetectionString(): void
    {
        $this->bag->add('string value');
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_STR, $params[':param0']['type']);
    }

    public function testTypeDetectionInteger(): void
    {
        $this->bag->add(42);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_INT, $params[':param0']['type']);
    }

    public function testTypeDetectionBoolean(): void
    {
        $this->bag->add(true);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_BOOL, $params[':param0']['type']);
        
        $this->bag->clear();
        $this->bag->add(false);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_BOOL, $params[':param0']['type']);
    }

    public function testTypeDetectionNull(): void
    {
        $this->bag->add(null);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_NULL, $params[':param0']['type']);
    }

    public function testTypeDetectionResource(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->bag->add($resource);
        fclose($resource);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_LOB, $params[':param0']['type']);
    }

    public function testTypeDetectionFloat(): void
    {
        $this->bag->add(3.14);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_STR, $params[':param0']['type']);
    }

    public function testTypeDetectionArray(): void
    {
        $this->bag->add(['array']);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_STR, $params[':param0']['type']);
    }

    public function testTypeDetectionObject(): void
    {
        $this->bag->add(new \stdClass());
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_STR, $params[':param0']['type']);
    }

    public function testBind(): void
    {
        $this->bag->add('test', PDO::PARAM_STR);
        $this->bag->addNamed('id', 123, PDO::PARAM_INT);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(2))
            ->method('bindValue')
            ->with(
                $this->logicalOr(
                    $this->equalTo(':param0'),
                    $this->equalTo(':id')
                ),
                $this->logicalOr(
                    $this->equalTo('test'),
                    $this->equalTo(123)
                ),
                $this->logicalOr(
                    $this->equalTo(PDO::PARAM_STR),
                    $this->equalTo(PDO::PARAM_INT)
                )
            );
        
        $this->bag->bind($statement);
    }

    public function testGetNamedValues(): void
    {
        $this->bag->add('value1');
        $this->bag->addNamed('param2', 'value2');
        $this->bag->add(123);
        
        $values = $this->bag->getNamedValues();
        
        $expected = [
            ':param0' => 'value1',
            ':param2' => 'value2',
            ':param1' => 123
        ];
        
        $this->assertEquals($expected, $values);
    }

    public function testMerge(): void
    {
        $this->bag->add('first');
        $this->bag->addNamed('named1', 'value1');
        
        $otherBag = new QueryParameterBag();
        $otherBag->add('second');
        $otherBag->addNamed('named2', 'value2');
        $otherBag->addNamed('named1', 'overwritten'); // Should overwrite
        
        $mergedBag = $this->bag->merge($otherBag);
        
        // Original bag should be unchanged
        $this->assertEquals(2, $this->bag->count());
        $originalValues = $this->bag->getNamedValues();
        $this->assertEquals('value1', $originalValues[':named1']);
        
        // Merged bag should have all parameters
        // Both bags have :param0, so otherBag overwrites
        $this->assertEquals(3, $mergedBag->count());
        $mergedValues = $mergedBag->getNamedValues();
        
        $this->assertEquals('second', $mergedValues[':param0']); // Overwritten by other bag
        $this->assertEquals('overwritten', $mergedValues[':named1']);
        $this->assertEquals('value2', $mergedValues[':named2']);
    }

    public function testMergeWithEmptyBag(): void
    {
        $this->bag->add('test');
        
        $emptyBag = new QueryParameterBag();
        $mergedBag = $this->bag->merge($emptyBag);
        
        $this->assertEquals(1, $mergedBag->count());
        $this->assertEquals($this->bag->getNamedValues(), $mergedBag->getNamedValues());
    }

    public function testMergeEmptyWithNonEmpty(): void
    {
        $otherBag = new QueryParameterBag();
        $otherBag->add('test');
        
        $mergedBag = $this->bag->merge($otherBag);
        
        $this->assertEquals(1, $mergedBag->count());
        $this->assertEquals($otherBag->getNamedValues(), $mergedBag->getNamedValues());
    }

    public function testClear(): void
    {
        $this->bag->add('test1');
        $this->bag->add('test2');
        $this->bag->addNamed('named', 'value');
        
        $this->assertEquals(3, $this->bag->count());
        $this->assertFalse($this->bag->isEmpty());
        
        $this->bag->clear();
        
        $this->assertEquals(0, $this->bag->count());
        $this->assertTrue($this->bag->isEmpty());
        
        // Parameter counter should also be reset
        $placeholder = $this->bag->add('new param');
        $this->assertEquals(':param0', $placeholder);
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->bag->count());
        
        $this->bag->add('test');
        $this->assertEquals(1, $this->bag->count());
        
        $this->bag->addNamed('param', 'value');
        $this->assertEquals(2, $this->bag->count());
        
        // Adding with same name should not increase count
        $this->bag->addNamed('param', 'new value');
        $this->assertEquals(2, $this->bag->count());
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->bag->isEmpty());
        
        $this->bag->add('test');
        $this->assertFalse($this->bag->isEmpty());
        
        $this->bag->clear();
        $this->assertTrue($this->bag->isEmpty());
    }

    public function testToArray(): void
    {
        $this->bag->add('string', PDO::PARAM_STR);
        $this->bag->add(123, PDO::PARAM_INT);
        $this->bag->addNamed('custom', true, PDO::PARAM_BOOL);
        
        $array = $this->bag->toArray();
        
        $expected = [
            ':param0' => ['value' => 'string', 'type' => PDO::PARAM_STR],
            ':param1' => ['value' => 123, 'type' => PDO::PARAM_INT],
            ':custom' => ['value' => true, 'type' => PDO::PARAM_BOOL]
        ];
        
        $this->assertEquals($expected, $array);
    }

    public function testToArrayEmpty(): void
    {
        $array = $this->bag->toArray();
        
        $this->assertEquals([], $array);
    }

    public function testComplexWorkflow(): void
    {
        // Simulate a complex query building scenario
        $this->bag->addNamed('table_name', 'users');
        $this->bag->add(18); // age condition
        $this->bag->addNamed('status', 'active');
        $this->bag->add(10); // limit
        
        $this->assertEquals(4, $this->bag->count());
        
        $values = $this->bag->getNamedValues();
        $this->assertEquals([
            ':table_name' => 'users',
            ':param0' => 18,
            ':status' => 'active',
            ':param1' => 10
        ], $values);
        
        // Create another bag for additional conditions
        $additionalBag = new QueryParameterBag();
        $additionalBag->addNamed('email_domain', '@example.com');
        $additionalBag->add(false); // is_deleted
        
        // Merge them
        $finalBag = $this->bag->merge($additionalBag);
        
        // Should have 5 parameters: 4 from original + 2 from additional - 1 overlap if any
        $this->assertEquals(5, $finalBag->count());
        
        // Test binding simulation
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(5))
            ->method('bindValue');
        
        $finalBag->bind($statement);
    }

    public function testParameterCounterIncrementsCorrectly(): void
    {
        $placeholder1 = $this->bag->add('first');
        $placeholder2 = $this->bag->add('second');
        $placeholder3 = $this->bag->add('third');
        
        $this->assertEquals(':param0', $placeholder1);
        $this->assertEquals(':param1', $placeholder2);
        $this->assertEquals(':param2', $placeholder3);
        
        // Named parameters shouldn't affect the counter
        $this->bag->addNamed('named', 'value');
        $placeholder4 = $this->bag->add('fourth');
        
        $this->assertEquals(':param3', $placeholder4);
    }

    public function testMixedParameterTypes(): void
    {
        $this->bag->add('string');           // PDO::PARAM_STR
        $this->bag->add(42);                // PDO::PARAM_INT
        $this->bag->add(true);              // PDO::PARAM_BOOL
        $this->bag->add(null);              // PDO::PARAM_NULL
        $this->bag->add(3.14);              // PDO::PARAM_STR (float)
        $this->bag->add(['array']);         // PDO::PARAM_STR (array)
        
        $params = $this->bag->toArray();
        
        $this->assertEquals(PDO::PARAM_STR, $params[':param0']['type']);
        $this->assertEquals(PDO::PARAM_INT, $params[':param1']['type']);
        $this->assertEquals(PDO::PARAM_BOOL, $params[':param2']['type']);
        $this->assertEquals(PDO::PARAM_NULL, $params[':param3']['type']);
        $this->assertEquals(PDO::PARAM_STR, $params[':param4']['type']);
        $this->assertEquals(PDO::PARAM_STR, $params[':param5']['type']);
    }

    public function testCloneableViaMerge(): void
    {
        $this->bag->add('original');
        $this->bag->addNamed('name', 'value');
        
        $emptyBag = new QueryParameterBag();
        $cloned = $this->bag->merge($emptyBag);
        
        // Modify original
        $this->bag->addNamed('name', 'modified');
        
        // Cloned should be unchanged
        $clonedValues = $cloned->getNamedValues();
        $this->assertEquals('value', $clonedValues[':name']);
        
        $originalValues = $this->bag->getNamedValues();
        $this->assertEquals('modified', $originalValues[':name']);
    }

    public function testSpecialCharactersInValues(): void
    {
        $specialValues = [
            'unicode' => 'Héllo Wörld! 🌍',
            'sql_injection' => "'; DROP TABLE users; --",
            'quotes' => "It's a \"test\" value",
            'newlines' => "Line 1\nLine 2\r\nLine 3",
            'tabs' => "Column1\tColumn2\tColumn3"
        ];
        
        foreach ($specialValues as $key => $value) {
            $this->bag->addNamed($key, $value);
        }
        
        $values = $this->bag->getNamedValues();
        
        foreach ($specialValues as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $values[':' . $key]);
        }
    }

    public function testEmptyStringParameter(): void
    {
        $this->bag->add('');
        $this->bag->addNamed('empty', '');
        
        $values = $this->bag->getNamedValues();
        
        $this->assertEquals('', $values[':param0']);
        $this->assertEquals('', $values[':empty']);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_STR, $params[':param0']['type']);
        $this->assertEquals(PDO::PARAM_STR, $params[':empty']['type']);
    }

    public function testZeroValues(): void
    {
        $this->bag->add(0);
        $this->bag->add(0.0);
        $this->bag->add('0');
        
        $values = $this->bag->getNamedValues();
        
        $this->assertEquals(0, $values[':param0']);
        $this->assertEquals(0.0, $values[':param1']);
        $this->assertEquals('0', $values[':param2']);
        
        $params = $this->bag->toArray();
        $this->assertEquals(PDO::PARAM_INT, $params[':param0']['type']);
        $this->assertEquals(PDO::PARAM_STR, $params[':param1']['type']);
        $this->assertEquals(PDO::PARAM_STR, $params[':param2']['type']);
    }
}