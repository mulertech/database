<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\ValueProcessor;

use MulerTech\Database\ORM\ValueProcessor\ValueProcessorInterface;
use MulerTech\Database\ORM\ValueProcessor\ColumnTypeValueProcessor;
use MulerTech\Database\ORM\ValueProcessor\PhpTypeValueProcessor;
use PHPUnit\Framework\TestCase;

class ValueProcessorInterfaceTest extends TestCase
{
    public function testColumnTypeValueProcessorImplementsInterface(): void
    {
        $processor = new ColumnTypeValueProcessor();
        
        self::assertInstanceOf(ValueProcessorInterface::class, $processor);
    }

    public function testPhpTypeValueProcessorImplementsInterface(): void
    {
        $processor = new PhpTypeValueProcessor();
        
        self::assertInstanceOf(ValueProcessorInterface::class, $processor);
    }

    public function testInterfaceHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(ValueProcessorInterface::class);
        
        $expectedMethods = [
            'convertToColumnValue',
            'convertToPhpValue',
            'isValidType',
            'getSupportedTypes',
            'normalizeType',
            'getDefaultValue'
        ];
        
        $actualMethods = array_map(fn($method) => $method->getName(), $reflection->getMethods());
        
        foreach ($expectedMethods as $expectedMethod) {
            self::assertContains($expectedMethod, $actualMethods, "Method $expectedMethod not found in interface");
        }
    }

    public function testInterfaceMethodSignatures(): void
    {
        $reflection = new \ReflectionClass(ValueProcessorInterface::class);
        
        $convertToColumnMethod = $reflection->getMethod('convertToColumnValue');
        self::assertEquals(2, $convertToColumnMethod->getNumberOfParameters());
        
        $convertToPhpMethod = $reflection->getMethod('convertToPhpValue');
        self::assertEquals(2, $convertToPhpMethod->getNumberOfParameters());
        
        $isValidTypeMethod = $reflection->getMethod('isValidType');
        self::assertEquals(1, $isValidTypeMethod->getNumberOfParameters());
        
        $getSupportedTypesMethod = $reflection->getMethod('getSupportedTypes');
        self::assertEquals(0, $getSupportedTypesMethod->getNumberOfParameters());
        
        $normalizeTypeMethod = $reflection->getMethod('normalizeType');
        self::assertEquals(1, $normalizeTypeMethod->getNumberOfParameters());
        
        $getDefaultValueMethod = $reflection->getMethod('getDefaultValue');
        self::assertEquals(1, $getDefaultValueMethod->getNumberOfParameters());
    }

    public function testColumnTypeProcessorMethodsExist(): void
    {
        $processor = new ColumnTypeValueProcessor();
        $reflection = new \ReflectionClass($processor);
        
        $interfaceReflection = new \ReflectionClass(ValueProcessorInterface::class);
        
        foreach ($interfaceReflection->getMethods() as $method) {
            self::assertTrue(
                $reflection->hasMethod($method->getName()),
                "Method {$method->getName()} not implemented in ColumnTypeValueProcessor"
            );
        }
    }

    public function testPhpTypeProcessorMethodsExist(): void
    {
        $processor = new PhpTypeValueProcessor();
        $reflection = new \ReflectionClass($processor);
        
        $interfaceReflection = new \ReflectionClass(ValueProcessorInterface::class);
        
        foreach ($interfaceReflection->getMethods() as $method) {
            self::assertTrue(
                $reflection->hasMethod($method->getName()),
                "Method {$method->getName()} not implemented in PhpTypeValueProcessor"
            );
        }
    }

    public function testInterfaceIsInterface(): void
    {
        $reflection = new \ReflectionClass(ValueProcessorInterface::class);
        
        self::assertTrue($reflection->isInterface());
    }

    public function testInterfaceConstants(): void
    {
        $reflection = new \ReflectionClass(ValueProcessorInterface::class);
        $constants = $reflection->getConstants();
        
        self::assertIsArray($constants);
    }

    public function testProcessorCanBeTypedAsInterface(): void
    {
        $columnProcessor = new ColumnTypeValueProcessor();
        $phpProcessor = new PhpTypeValueProcessor();
        
        $this->processWithInterface($columnProcessor);
        $this->processWithInterface($phpProcessor);
        
        self::assertTrue(true);
    }

    public function testInterfaceMethodReturnTypes(): void
    {
        $reflection = new \ReflectionClass(ValueProcessorInterface::class);
        
        $convertToColumnMethod = $reflection->getMethod('convertToColumnValue');
        $returnType = $convertToColumnMethod->getReturnType();
        self::assertNotNull($returnType);
        
        $isValidTypeMethod = $reflection->getMethod('isValidType');
        $returnType = $isValidTypeMethod->getReturnType();
        self::assertNotNull($returnType);
        
        $getSupportedTypesMethod = $reflection->getMethod('getSupportedTypes');
        $returnType = $getSupportedTypesMethod->getReturnType();
        self::assertNotNull($returnType);
    }

    public function testImplementationCompatibility(): void
    {
        $columnProcessor = new ColumnTypeValueProcessor();
        $phpProcessor = new PhpTypeValueProcessor();
        
        $testValue = 'test';
        $testType = 'string';
        
        $columnResult = $columnProcessor->convertToColumnValue($testValue, $testType);
        $phpResult = $phpProcessor->convertToPhpValue($testValue, $testType);
        
        self::assertEquals($testValue, $columnResult);
        self::assertEquals($testValue, $phpResult);
        
        self::assertTrue($columnProcessor->isValidType($testType));
        self::assertTrue($phpProcessor->isValidType($testType));
        
        self::assertIsArray($columnProcessor->getSupportedTypes());
        self::assertIsArray($phpProcessor->getSupportedTypes());
    }

    private function processWithInterface(ValueProcessorInterface $processor): void
    {
        $processor->convertToColumnValue('test', 'string');
        $processor->convertToPhpValue('test', 'string');
        $processor->isValidType('string');
        $processor->getSupportedTypes();
        $processor->normalizeType('string');
        $processor->getDefaultValue('string');
    }
}