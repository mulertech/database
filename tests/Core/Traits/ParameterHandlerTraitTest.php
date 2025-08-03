<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Traits;

use MulerTech\Database\Core\Traits\ParameterHandlerTrait;
use MulerTech\Database\Database\Interface\Statement;
use MulerTech\Database\Tests\Files\Traits\TestClassWithParameterHandlerTrait;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParameterHandlerTrait::class)]
final class ParameterHandlerTraitTest extends TestCase
{
    private TestClassWithParameterHandlerTrait $handler;

    protected function setUp(): void
    {
        $this->handler = new TestClassWithParameterHandlerTrait();
    }

    public function testInitialState(): void
    {
        $this->assertEquals([], $this->handler->getNamedParameters());
        $this->assertEquals([], $this->handler->getDynamicParameters());
    }

    public function testBindParametersWithNamedArray(): void
    {
        // Set up named parameters with array structure
        $this->handler->setNamedParameters([
            ':param1' => ['value' => 'test', 'type' => PDO::PARAM_STR],
            ':param2' => ['value' => 123, 'type' => PDO::PARAM_INT],
            ':param3' => ['value' => true, 'type' => PDO::PARAM_BOOL],
        ]);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(3))
            ->method('bindValue')
            ->with(
                $this->logicalOr(
                    $this->equalTo(':param1'),
                    $this->equalTo(':param2'),
                    $this->equalTo(':param3')
                ),
                $this->logicalOr(
                    $this->equalTo('test'),
                    $this->equalTo(123),
                    $this->equalTo(true)
                ),
                $this->logicalOr(
                    $this->equalTo(PDO::PARAM_STR),
                    $this->equalTo(PDO::PARAM_INT),
                    $this->equalTo(PDO::PARAM_BOOL)
                )
            );
        
        $this->handler->callBindParameters($statement);
    }

    public function testBindParametersWithInvalidNamedStructure(): void
    {
        // Set up named parameters with invalid structure (missing type or value)
        $this->handler->setNamedParameters([
            ':param1' => ['value' => 'test'], // Missing type
            ':param2' => ['type' => PDO::PARAM_INT], // Missing value
            ':param3' => 'invalid_structure', // Not an array
        ]);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->never())
            ->method('bindValue');
        
        $this->handler->callBindParameters($statement);
    }

    public function testBindParametersWithInvalidTypeInNamedParameters(): void
    {
        // Set up named parameters with string type (should default to PDO::PARAM_STR)
        $this->handler->setNamedParameters([
            ':param1' => ['value' => 'test', 'type' => 'invalid_type'],
        ]);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':param1', 'test', PDO::PARAM_STR);
        
        $this->handler->callBindParameters($statement);
    }

    public function testBindParametersWithDynamicParameters(): void
    {
        // Set up dynamic parameters
        $this->handler->setDynamicParameters(['value1', 'value2', 123]);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(3))
            ->method('bindValue')
            ->with(
                $this->logicalOr(
                    $this->equalTo(1),
                    $this->equalTo(2),
                    $this->equalTo(3)
                ),
                $this->logicalOr(
                    $this->equalTo('value1'),
                    $this->equalTo('value2'),
                    $this->equalTo(123)
                )
            );
        
        $this->handler->callBindParameters($statement);
    }

    public function testBindParametersWithBothTypes(): void
    {
        // Set up both named and dynamic parameters
        $this->handler->setNamedParameters([
            ':named' => ['value' => 'named_value', 'type' => PDO::PARAM_STR],
        ]);
        $this->handler->setDynamicParameters(['dynamic_value']);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(2))
            ->method('bindValue')
            ->with(
                $this->logicalOr(
                    $this->equalTo(':named'),
                    $this->equalTo(1)
                ),
                $this->logicalOr(
                    $this->equalTo('named_value'),
                    $this->equalTo('dynamic_value')
                ),
                $this->anything()
            );
        
        $this->handler->callBindParameters($statement);
    }

    public function testGetNamedParameters(): void
    {
        $params = [':param1' => 'value1', ':param2' => 'value2'];
        $this->handler->setNamedParameters($params);
        
        $this->assertEquals($params, $this->handler->getNamedParameters());
    }

    public function testGetDynamicParameters(): void
    {
        $params = ['value1', 'value2', 123];
        $this->handler->setDynamicParameters($params);
        
        $this->assertEquals($params, $this->handler->getDynamicParameters());
    }

    public function testResetParameters(): void
    {
        $this->handler->setNamedParameters([':param' => 'value']);
        $this->handler->setDynamicParameters(['value']);
        $this->handler->setParameterCounter(5);
        
        $this->handler->callResetParameters();
        
        $this->assertEquals([], $this->handler->getNamedParameters());
        $this->assertEquals([], $this->handler->getDynamicParameters());
        $this->assertEquals(0, $this->handler->getParameterCounter());
    }

    public function testDetectParameterTypeString(): void
    {
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType('string'));
    }

    public function testDetectParameterTypeInteger(): void
    {
        $this->assertEquals(PDO::PARAM_INT, $this->handler->callDetectParameterType(42));
    }

    public function testDetectParameterTypeBoolean(): void
    {
        $this->assertEquals(PDO::PARAM_BOOL, $this->handler->callDetectParameterType(true));
        $this->assertEquals(PDO::PARAM_BOOL, $this->handler->callDetectParameterType(false));
    }

    public function testDetectParameterTypeNull(): void
    {
        $this->assertEquals(PDO::PARAM_NULL, $this->handler->callDetectParameterType(null));
    }

    public function testDetectParameterTypeFloat(): void
    {
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType(3.14));
    }

    public function testDetectParameterTypeArray(): void
    {
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType(['array']));
    }

    public function testDetectParameterTypeObject(): void
    {
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType(new \stdClass()));
    }

    public function testMergeNamedParameters(): void
    {
        $this->handler->setNamedParameters([':param1' => 'value1']);
        
        $result = $this->handler->callMergeNamedParameters([':param2' => 'value2']);
        
        $this->assertSame($this->handler, $result);
        $this->assertEquals([
            ':param1' => 'value1',
            ':param2' => 'value2'
        ], $this->handler->getNamedParameters());
    }

    public function testMergeNamedParametersOverwrite(): void
    {
        $this->handler->setNamedParameters([':param1' => 'original']);
        
        $this->handler->callMergeNamedParameters([':param1' => 'overwritten']);
        
        $this->assertEquals([':param1' => 'overwritten'], $this->handler->getNamedParameters());
    }

    public function testMergeDynamicParameters(): void
    {
        $this->handler->setDynamicParameters(['value1']);
        
        $result = $this->handler->callMergeDynamicParameters(['value2']);
        
        $this->assertSame($this->handler, $result);
        $this->assertEquals(['value1', 'value2'], $this->handler->getDynamicParameters());
    }

    public function testMergeDynamicParametersWithIndexes(): void
    {
        $this->handler->setDynamicParameters([0 => 'value1']);
        
        $this->handler->callMergeDynamicParameters([1 => 'value2', 2 => 'value3']);
        
        $this->assertEquals([
            0 => 'value1',
            1 => 'value2',
            2 => 'value3'
        ], $this->handler->getDynamicParameters());
    }

    public function testComplexParameterWorkflow(): void
    {
        // Simulate a complex query building workflow
        $this->handler->setNamedParameters([
            ':table' => ['value' => 'users', 'type' => PDO::PARAM_STR]
        ]);
        
        $this->handler->callMergeDynamicParameters([18, 'active']);
        
        $this->handler->callMergeNamedParameters([
            ':limit' => ['value' => 10, 'type' => PDO::PARAM_INT]
        ]);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(4))
            ->method('bindValue');
        
        $this->handler->callBindParameters($statement);
        
        // Verify final state
        $namedParams = $this->handler->getNamedParameters();
        $this->assertArrayHasKey(':table', $namedParams);
        $this->assertArrayHasKey(':limit', $namedParams);
        
        $dynamicParams = $this->handler->getDynamicParameters();
        $this->assertEquals([18, 'active'], $dynamicParams);
    }

    public function testEmptyParameterBinding(): void
    {
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->never())
            ->method('bindValue');
        
        $this->handler->callBindParameters($statement);
    }

    public function testParameterCounterManipulation(): void
    {
        $this->assertEquals(0, $this->handler->getParameterCounter());
        
        $this->handler->setParameterCounter(42);
        $this->assertEquals(42, $this->handler->getParameterCounter());
        
        $this->handler->callResetParameters();
        $this->assertEquals(0, $this->handler->getParameterCounter());
    }

    public function testChainedMergeOperations(): void
    {
        $result1 = $this->handler->callMergeNamedParameters([':param1' => 'value1']);
        $result2 = $result1->callMergeDynamicParameters(['dynamic1']);
        $result3 = $result2->callMergeNamedParameters([':param2' => 'value2']);
        $result4 = $result3->callMergeDynamicParameters(['dynamic2']);
        
        $this->assertSame($this->handler, $result1);
        $this->assertSame($this->handler, $result2);
        $this->assertSame($this->handler, $result3);
        $this->assertSame($this->handler, $result4);
        
        $this->assertEquals([
            ':param1' => 'value1',
            ':param2' => 'value2'
        ], $this->handler->getNamedParameters());
        
        $this->assertEquals(['dynamic1', 'dynamic2'], $this->handler->getDynamicParameters());
    }

    public function testNamedParametersWithSpecialCharacters(): void
    {
        $specialParams = [
            ':unicode' => ['value' => 'Héllo Wörld! 🌍', 'type' => PDO::PARAM_STR],
            ':quotes' => ['value' => "It's a \"test\"", 'type' => PDO::PARAM_STR],
            ':newlines' => ['value' => "Line1\nLine2", 'type' => PDO::PARAM_STR],
        ];
        
        $this->handler->setNamedParameters($specialParams);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(3))
            ->method('bindValue');
        
        $this->handler->callBindParameters($statement);
        
        $this->assertEquals($specialParams, $this->handler->getNamedParameters());
    }

    public function testDynamicParametersWithMixedTypes(): void
    {
        $mixedParams = ['string', 42, true, null, 3.14, ['array']];
        
        $this->handler->setDynamicParameters($mixedParams);
        
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(6))
            ->method('bindValue');
        
        $this->handler->callBindParameters($statement);
        
        $this->assertEquals($mixedParams, $this->handler->getDynamicParameters());
    }

    public function testTypeDetectionWithEdgeCases(): void
    {
        // Test zero values
        $this->assertEquals(PDO::PARAM_INT, $this->handler->callDetectParameterType(0));
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType(0.0));
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType('0'));
        
        // Test empty values
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType(''));
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType([]));
        
        // Test negative values
        $this->assertEquals(PDO::PARAM_INT, $this->handler->callDetectParameterType(-42));
        $this->assertEquals(PDO::PARAM_STR, $this->handler->callDetectParameterType(-3.14));
    }

    public function testParameterBindingOrder(): void
    {
        // Ensure binding order is predictable
        $this->handler->setNamedParameters([
            ':z_param' => ['value' => 'z_value', 'type' => PDO::PARAM_STR],
            ':a_param' => ['value' => 'a_value', 'type' => PDO::PARAM_STR],
        ]);
        
        $this->handler->setDynamicParameters(['first', 'second']);
        
        $bindCalls = [];
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(4))
            ->method('bindValue')
            ->willReturnCallback(function ($param, $value) use (&$bindCalls) {
                $bindCalls[] = ['param' => $param, 'value' => $value];
                return true;
            });
        
        $this->handler->callBindParameters($statement);
        
        // Verify that named parameters are bound first, then dynamic
        $this->assertCount(4, $bindCalls);
        
        // First two should be named parameters
        $this->assertEquals(':z_param', $bindCalls[0]['param']);
        $this->assertEquals(':a_param', $bindCalls[1]['param']);
        
        // Last two should be dynamic parameters (1-indexed)
        $this->assertEquals(1, $bindCalls[2]['param']);
        $this->assertEquals(2, $bindCalls[3]['param']);
    }
}

