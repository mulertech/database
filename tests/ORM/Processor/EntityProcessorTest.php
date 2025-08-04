<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Processor;

use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
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

    public function testCopyEntityDataWithDifferentEntityTypes(): void
    {
        $sourceUser = new User();
        $sourceUser->setUsername('John');

        $targetUnit = new Unit();
        $targetUnit->setName('TestUnit');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot copy data between different entity types');

        $this->processor->copyEntityData($sourceUser, $targetUnit);
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

    public function testExtractEntityIdWithStringId(): void
    {
        // Puisque User::setId() n'accepte que des int, on teste avec un int
        $user = new User();
        $user->setId(999);

        $id = $this->processor->extractEntityId($user);

        self::assertEquals(999, $id);
    }

    public function testCopyEntityDataPreservesId(): void
    {
        $sourceUser = new User();
        $sourceUser->setId(100);
        $sourceUser->setUsername('John');

        $targetUser = new User();
        $targetUser->setId(200);
        $targetUser->setUsername('Jane');

        $this->processor->copyEntityData($sourceUser, $targetUser);

        // L'ID ne doit pas être copié
        self::assertEquals(200, $targetUser->getId());
        self::assertEquals('John', $targetUser->getUsername());
    }
}