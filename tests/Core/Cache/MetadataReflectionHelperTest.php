<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use MulerTech\Database\Core\Cache\MetadataReflectionHelper;
use MulerTech\Database\Tests\Files\Cache\Reflection\TestClassWithoutConstructor;
use MulerTech\Database\Tests\Files\Cache\Reflection\TestClassWithUnionType;
use MulerTech\Database\Tests\Files\Cache\Reflection\TestReflectionClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

#[CoversClass(MetadataReflectionHelper::class)]
final class MetadataReflectionHelperTest extends TestCase
{
    private MetadataReflectionHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new MetadataReflectionHelper();
    }

    public function testGetConstructorParamsWithConstructor(): void
    {
        $reflection = new ReflectionClass(TestReflectionClass::class);
        
        $params = $this->helper->getConstructorParams($reflection);
        
        $this->assertCount(3, $params);
        
        $this->assertEquals('param1', $params[0]['name']);
        $this->assertFalse($params[0]['isOptional']);
        $this->assertFalse($params[0]['hasDefault']);
        $this->assertNull($params[0]['default']);
        
        $this->assertEquals('param2', $params[1]['name']);
        $this->assertTrue($params[1]['isOptional']);
        $this->assertTrue($params[1]['hasDefault']);
        $this->assertEquals(10, $params[1]['default']);
        
        $this->assertEquals('param3', $params[2]['name']);
        $this->assertTrue($params[2]['isOptional']);
        $this->assertTrue($params[2]['hasDefault']);
        $this->assertNull($params[2]['default']);
    }

    public function testGetConstructorParamsWithoutConstructor(): void
    {
        $reflection = new ReflectionClass(TestClassWithoutConstructor::class);
        
        $params = $this->helper->getConstructorParams($reflection);
        
        $this->assertEmpty($params);
    }

    public function testGetPropertiesMetadata(): void
    {
        $reflection = new ReflectionClass(TestReflectionClass::class);
        
        $properties = $this->helper->getPropertiesMetadata($reflection);
        
        $this->assertCount(4, $properties); // Excludes static property
        
        $this->assertArrayHasKey('publicProperty', $properties);
        $this->assertTrue($properties['publicProperty']['isPublic']);
        $this->assertFalse($properties['publicProperty']['isProtected']);
        $this->assertFalse($properties['publicProperty']['isPrivate']);
        $this->assertEquals('string', $properties['publicProperty']['type']);
        
        $this->assertArrayHasKey('protectedProperty', $properties);
        $this->assertFalse($properties['protectedProperty']['isPublic']);
        $this->assertTrue($properties['protectedProperty']['isProtected']);
        $this->assertFalse($properties['protectedProperty']['isPrivate']);
        $this->assertEquals('int', $properties['protectedProperty']['type']);
        
        $this->assertArrayHasKey('privateProperty', $properties);
        $this->assertFalse($properties['privateProperty']['isPublic']);
        $this->assertFalse($properties['privateProperty']['isProtected']);
        $this->assertTrue($properties['privateProperty']['isPrivate']);
        $this->assertEquals('bool', $properties['privateProperty']['type']);
        
        $this->assertArrayHasKey('noTypeProperty', $properties);
        $this->assertNull($properties['noTypeProperty']['type']);
        
        // Static property should not be included
        $this->assertArrayNotHasKey('staticProperty', $properties);
    }

    public function testGetPropertyTypeNameWithNull(): void
    {
        $result = $this->helper->getPropertyTypeName(null);
        
        $this->assertNull($result);
    }

    public function testGetPropertyTypeNameWithNamedType(): void
    {
        $namedType = $this->createMock(ReflectionNamedType::class);
        $namedType->method('getName')->willReturn('string');
        
        $result = $this->helper->getPropertyTypeName($namedType);
        
        $this->assertEquals('string', $result);
    }

    public function testGetPropertyTypeNameWithUnionType(): void
    {
        // Create mock union type
        $namedType1 = $this->createMock(ReflectionNamedType::class);
        $namedType1->method('getName')->willReturn('string');
        
        $namedType2 = $this->createMock(ReflectionNamedType::class);
        $namedType2->method('getName')->willReturn('int');
        
        $unionType = $this->createMock(ReflectionUnionType::class);
        $unionType->method('getTypes')->willReturn([$namedType1, $namedType2]);
        
        $result = $this->helper->getPropertyTypeName($unionType);
        
        $this->assertEquals('string|int', $result);
    }

    public function testGetPropertyTypeNameWithIntersectionType(): void
    {
        // Create mock intersection type
        $namedType1 = $this->createMock(ReflectionNamedType::class);
        $namedType1->method('getName')->willReturn('Interface1');
        
        $namedType2 = $this->createMock(ReflectionNamedType::class);
        $namedType2->method('getName')->willReturn('Interface2');
        
        $intersectionType = $this->createMock(ReflectionIntersectionType::class);
        $intersectionType->method('getTypes')->willReturn([$namedType1, $namedType2]);
        
        $result = $this->helper->getPropertyTypeName($intersectionType);
        
        $this->assertEquals('Interface1&Interface2', $result);
    }

    public function testGetPropertyTypeNameWithUnknownType(): void
    {
        $unknownType = $this->createMock(ReflectionType::class);
        
        $result = $this->helper->getPropertyTypeName($unknownType);
        
        $this->assertNull($result);
    }

    public function testBuildReflectionData(): void
    {
        $reflection = new ReflectionClass(TestReflectionClass::class);
        
        $data = $this->helper->buildReflectionData($reflection);
        
        $this->assertArrayHasKey('isInstantiable', $data);
        $this->assertArrayHasKey('hasConstructor', $data);
        $this->assertArrayHasKey('constructorParams', $data);
        $this->assertArrayHasKey('properties', $data);
        
        $this->assertTrue($data['isInstantiable']);
        $this->assertTrue($data['hasConstructor']);
        $this->assertCount(3, $data['constructorParams']);
        $this->assertCount(4, $data['properties']);
    }

    public function testBuildReflectionDataWithoutConstructor(): void
    {
        $reflection = new ReflectionClass(TestClassWithoutConstructor::class);
        
        $data = $this->helper->buildReflectionData($reflection);
        
        $this->assertTrue($data['isInstantiable']);
        $this->assertFalse($data['hasConstructor']);
        $this->assertEmpty($data['constructorParams']);
        $this->assertCount(1, $data['properties']);
    }

    public function testRealUnionTypeWithTestClass(): void
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Union types require PHP 8.0+');
        }
        
        $reflection = new ReflectionClass(TestClassWithUnionType::class);
        $properties = $this->helper->getPropertiesMetadata($reflection);
        
        $this->assertArrayHasKey('unionProperty', $properties);
        $this->assertEquals('string|int', $properties['unionProperty']['type']);
    }

    public function testGetPropertyTypeNameHandlesComplexUnionTypes(): void
    {
        // Test with non-named types in union
        $namedType = $this->createMock(ReflectionNamedType::class);
        $namedType->method('getName')->willReturn('string');
        
        $otherType = $this->createMock(ReflectionType::class);
        
        $unionType = $this->createMock(ReflectionUnionType::class);
        $unionType->method('getTypes')->willReturn([$namedType, $otherType]);
        
        $result = $this->helper->getPropertyTypeName($unionType);
        
        // Should handle the case where one type is not ReflectionNamedType
        $this->assertStringContainsString('string', $result);
    }

    public function testGetPropertyTypeNameHandlesComplexIntersectionTypes(): void
    {
        // Test with non-named types in intersection
        $namedType = $this->createMock(ReflectionNamedType::class);
        $namedType->method('getName')->willReturn('Interface1');
        
        $otherType = $this->createMock(ReflectionType::class);
        
        $intersectionType = $this->createMock(ReflectionIntersectionType::class);
        $intersectionType->method('getTypes')->willReturn([$namedType, $otherType]);
        
        $result = $this->helper->getPropertyTypeName($intersectionType);
        
        // Should handle the case where one type is not ReflectionNamedType
        $this->assertStringContainsString('Interface1', $result);
    }

    public function testConstructorParamsWithVariousDefaultValues(): void
    {
        // Create a test class with various default value types
        $testClass = new class('test', 42, true, [1, 2, 3]) {
            public function __construct(
                string $stringParam = 'default',
                int $intParam = 0,
                bool $boolParam = false,
                array $arrayParam = []
            ) {
            }
        };
        
        $reflection = new ReflectionClass($testClass);
        $params = $this->helper->getConstructorParams($reflection);
        
        $this->assertCount(4, $params);
        
        $this->assertEquals('default', $params[0]['default']);
        $this->assertEquals(0, $params[1]['default']);
        $this->assertFalse($params[2]['default']);
        $this->assertEquals([], $params[3]['default']);
    }

    public function testAbstractClassReflection(): void
    {
        // Test with a regular class that simulates abstract behavior
        $regularClass = new class() {
            public string $property = 'test';
            
            public function concreteMethod(): string
            {
                return 'concrete';
            }
        };
        
        $reflection = new ReflectionClass($regularClass);
        $data = $this->helper->buildReflectionData($reflection);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('isInstantiable', $data);
        $this->assertTrue($data['isInstantiable']);
        $this->assertArrayHasKey('properties', $data);
        $this->assertArrayHasKey('property', $data['properties']);
    }
}