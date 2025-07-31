<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Attributes;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(MtEntity::class)]
class MtEntityTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $entity = new MtEntity();
        
        $this->assertNull($entity->repository);
        $this->assertNull($entity->tableName);
        $this->assertNull($entity->autoIncrement);
        $this->assertNull($entity->engine);
        $this->assertNull($entity->charset);
        $this->assertNull($entity->collation);
    }

    public function testConstructorWithAllParameters(): void
    {
        $entity = new MtEntity(
            repository: 'App\\Repository\\UserRepository',
            tableName: 'users',
            autoIncrement: 1000,
            engine: 'InnoDB',
            charset: 'utf8mb4',
            collation: 'utf8mb4_unicode_ci'
        );
        
        $this->assertEquals('App\\Repository\\UserRepository', $entity->repository);
        $this->assertEquals('users', $entity->tableName);
        $this->assertEquals(1000, $entity->autoIncrement);
        $this->assertEquals('InnoDB', $entity->engine);
        $this->assertEquals('utf8mb4', $entity->charset);
        $this->assertEquals('utf8mb4_unicode_ci', $entity->collation);
    }

    public function testAttributeTargetsClass(): void
    {
        $reflection = new ReflectionClass(MtEntity::class);
        $attributes = $reflection->getAttributes();
        
        $this->assertCount(1, $attributes);
        $this->assertEquals(\Attribute::class, $attributes[0]->getName());
        
        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_CLASS, $attributeInstance->flags);
    }

    public function testConstructorWithPartialParameters(): void
    {
        $entity = new MtEntity(
            tableName: 'products',
            engine: 'MyISAM'
        );
        
        $this->assertNull($entity->repository);
        $this->assertEquals('products', $entity->tableName);
        $this->assertNull($entity->autoIncrement);
        $this->assertEquals('MyISAM', $entity->engine);
        $this->assertNull($entity->charset);
        $this->assertNull($entity->collation);
    }

    public function testEntityWithCustomRepository(): void
    {
        $repositoryClass = 'MulerTech\\Database\\Tests\\Repository\\TestRepository';
        $entity = new MtEntity(repository: $repositoryClass);
        
        $this->assertEquals($repositoryClass, $entity->repository);
        $this->assertNull($entity->tableName);
    }

    public function testEntityWithTableNameOnly(): void
    {
        $entity = new MtEntity(tableName: 'user_profiles');
        
        $this->assertNull($entity->repository);
        $this->assertEquals('user_profiles', $entity->tableName);
        $this->assertNull($entity->autoIncrement);
        $this->assertNull($entity->engine);
        $this->assertNull($entity->charset);
        $this->assertNull($entity->collation);
    }

    public function testEntityWithAutoIncrementStartValue(): void
    {
        $entity = new MtEntity(
            tableName: 'orders',
            autoIncrement: 10000
        );
        
        $this->assertEquals('orders', $entity->tableName);
        $this->assertEquals(10000, $entity->autoIncrement);
    }

    public function testEntityWithCharsetAndCollation(): void
    {
        $entity = new MtEntity(
            tableName: 'multilingual_content',
            charset: 'utf8mb4',
            collation: 'utf8mb4_general_ci'
        );
        
        $this->assertEquals('multilingual_content', $entity->tableName);
        $this->assertEquals('utf8mb4', $entity->charset);
        $this->assertEquals('utf8mb4_general_ci', $entity->collation);
    }

    public function testEntityWithDifferentEngines(): void
    {
        $innodbEntity = new MtEntity(engine: 'InnoDB');
        $myisamEntity = new MtEntity(engine: 'MyISAM');
        $memoryEntity = new MtEntity(engine: 'MEMORY');
        
        $this->assertEquals('InnoDB', $innodbEntity->engine);
        $this->assertEquals('MyISAM', $myisamEntity->engine);
        $this->assertEquals('MEMORY', $memoryEntity->engine);
    }
}