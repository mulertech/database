<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Processor;

use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class EntityProcessorTest extends TestCase
{
    private EntityProcessor $processor;
    private IdentityMap $identityMap;
    private ChangeDetector $changeDetector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identityMap = new IdentityMap();
        $this->changeDetector = new ChangeDetector();
        $this->processor = new EntityProcessor($this->changeDetector, $this->identityMap);
    }

    public function testExtractEntityId(): void
    {
        $user = new User();
        $user->setId(123);
        
        $id = $this->processor->extractEntityId($user);
        
        self::assertEquals(123, $id);
    }

    public function testExtractEntityIdWithNullId(): void
    {
        $user = new User();
        
        $id = $this->processor->extractEntityId($user);
        
        self::assertNull($id);
    }

    public function testProcessEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setAge(25);
        
        $processedEntity = $this->processor->processEntity($user);
        
        self::assertSame($user, $processedEntity);
    }

    public function testCopyEntityData(): void
    {
        $sourceUser = new User();
        $sourceUser->setUsername('John');
        $sourceUser->setAge(25);
        
        $targetUser = new User();
        $targetUser->setUsername('Jane');
        $targetUser->setAge(30);
        
        $this->processor->copyEntityData($sourceUser, $targetUser);
        
        self::assertEquals('John', $targetUser->getUsername());
        self::assertEquals(25, $targetUser->getAge());
    }

    public function testCopyEntityDataWithRelations(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $sourceUser = new User();
        $sourceUser->setUsername('John');
        $sourceUser->setUnit($unit);
        
        $targetUser = new User();
        $targetUser->setUsername('Jane');
        
        $this->processor->copyEntityData($sourceUser, $targetUser);
        
        self::assertEquals('John', $targetUser->getUsername());
        self::assertSame($unit, $targetUser->getUnit());
        self::assertEquals('TestUnit', $targetUser->getUnit()->getName());
    }

    public function testCopyEntityDataWithNullValues(): void
    {
        $sourceUser = new User();
        $sourceUser->setUsername(null);
        $sourceUser->setAge(null);
        
        $targetUser = new User();
        $targetUser->setUsername('Jane');
        $targetUser->setAge(30);
        
        $this->processor->copyEntityData($sourceUser, $targetUser);
        
        self::assertNull($targetUser->getUsername());
        self::assertNull($targetUser->getAge());
    }

    public function testValidateEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $result = $this->processor->validateEntity($user);
        
        self::assertTrue($result);
    }

    public function testValidateEntityWithInvalidData(): void
    {
        $user = new User();
        
        $result = $this->processor->validateEntity($user);
        
        self::assertFalse($result);
    }

    public function testGetEntityHash(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $hash1 = $this->processor->getEntityHash($user);
        $hash2 = $this->processor->getEntityHash($user);
        
        self::assertIsInt($hash1);
        self::assertEquals($hash1, $hash2);
    }

    public function testGetEntityHashDifferentEntities(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        
        $user2 = new User();
        $user2->setUsername('Jane');
        
        $hash1 = $this->processor->getEntityHash($user1);
        $hash2 = $this->processor->getEntityHash($user2);
        
        self::assertNotEquals($hash1, $hash2);
    }

    public function testGetEntityHashSameData(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user1->setAge(25);
        
        $user2 = new User();
        $user2->setUsername('John');
        $user2->setAge(25);
        
        $hash1 = $this->processor->getEntityHash($user1);
        $hash2 = $this->processor->getEntityHash($user2);
        
        self::assertEquals($hash1, $hash2);
    }

    public function testPrepareEntityForPersistence(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setAge(25);
        
        $preparedEntity = $this->processor->prepareEntityForPersistence($user);
        
        self::assertSame($user, $preparedEntity);
    }

    public function testProcessEntityWithRelations(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        
        $processedEntity = $this->processor->processEntity($user);
        
        self::assertSame($user, $processedEntity);
        self::assertSame($unit, $processedEntity->getUnit());
    }

    public function testExtractEntityIdFromDifferentEntityTypes(): void
    {
        $user = new User();
        $user->setId(456);
        
        $unit = new Unit();
        $unit->setId(789);
        
        $userId = $this->processor->extractEntityId($user);
        $unitId = $this->processor->extractEntityId($unit);
        
        self::assertEquals(456, $userId);
        self::assertEquals(789, $unitId);
    }

    public function testGetEntityClass(): void
    {
        $user = new User();
        
        $className = $this->processor->getEntityClass($user);
        
        self::assertEquals(User::class, $className);
    }

    public function testIsValidEntity(): void
    {
        $user = new User();
        $stdClass = new \stdClass();
        
        self::assertTrue($this->processor->isValidEntity($user));
        self::assertFalse($this->processor->isValidEntity($stdClass));
    }

    public function testGetEntityProperties(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setAge(25);
        
        $properties = $this->processor->getEntityProperties($user);
        
        self::assertIsArray($properties);
        self::assertArrayHasKey('username', $properties);
        self::assertArrayHasKey('age', $properties);
        self::assertEquals('John', $properties['username']);
        self::assertEquals(25, $properties['age']);
    }

    public function testCompareEntities(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user1->setAge(25);
        
        $user2 = new User();
        $user2->setUsername('John');
        $user2->setAge(25);
        
        $user3 = new User();
        $user3->setUsername('Jane');
        $user3->setAge(30);
        
        self::assertTrue($this->processor->compareEntities($user1, $user2));
        self::assertFalse($this->processor->compareEntities($user1, $user3));
    }
}