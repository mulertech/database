<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Relations;

use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\Engine\Relations\LinkEntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\EntityNotMapped\EntityWithoutGetId;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class LinkEntityManagerTest extends TestCase
{
    private LinkEntityManager $linkEntityManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $stateManager = $this->createMock(StateManagerInterface::class);
        
        $this->linkEntityManager = new LinkEntityManager(
            $entityManager,
            $stateManager
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testFindExistingLinkRelationWithNullIds(): void
    {
        $user1 = new User(); // No ID set
        $user2 = new User(); // No ID set

        $manyToMany = new MtManyToMany(
            mappedBy: User::class,
            joinProperty: 'user1',
            inverseJoinProperty: 'user2'
        );

        $result = $this->linkEntityManager->findExistingLinkRelation($manyToMany, $user1, $user2);
        
        $this->assertNull($result);
    }

    public function testCreateLinkEntity(): void
    {
        $user1 = new User();
        $user1->setId(1);
        $user2 = new User();
        $user2->setId(2);

        $manyToMany = new MtManyToMany(
            mappedBy: User::class,
            joinProperty: 'user1',
            inverseJoinProperty: 'user2'
        );

        $linkEntity = $this->linkEntityManager->createLinkEntity($manyToMany, $user1, $user2);
        
        $this->assertInstanceOf(User::class, $linkEntity);
    }

    public function testCreateLinkEntityWithNullIds(): void
    {
        $user1 = new User(); // No ID
        $user2 = new User();
        $user2->setId(2);

        $manyToMany = new MtManyToMany(
            mappedBy: User::class,
            joinProperty: 'user1',
            inverseJoinProperty: 'user2'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot create link entity without IDs');
        
        $this->linkEntityManager->createLinkEntity($manyToMany, $user1, $user2);
    }

    public function testCreateLinkEntityWithInvalidJoinProperties(): void
    {
        $user1 = new User();
        $user1->setId(1);
        $user2 = new User();
        $user2->setId(2);

        $manyToMany = new MtManyToMany(
            mappedBy: User::class,
            joinProperty: null, // Invalid
            inverseJoinProperty: 'user2'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot create link entity without join properties');
        
        $this->linkEntityManager->createLinkEntity($manyToMany, $user1, $user2);
    }

    public function testClear(): void
    {
        // Clear should not throw any exceptions
        $this->linkEntityManager->clear();
    }

    /**
     * @throws ReflectionException
     */
    public function testFindExistingLinkRelationWithInvalidJoinProperties(): void
    {
        $user1 = new User();
        $user1->setId(1);
        $user2 = new User();
        $user2->setId(2);
        
        $manyToMany = new MtManyToMany(
            mappedBy: User::class,
            joinProperty: null, // This will trigger the echo in validateJoinProperties
            inverseJoinProperty: 'user2'
        );

        $result = $this->linkEntityManager->findExistingLinkRelation($manyToMany, $user1, $user2);
        
        $this->assertNull($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetIdWithEntityWithoutGetIdMethod(): void
    {
        // Use the test entity without getId method as suggested in the echo message
        $entityWithoutId = new EntityWithoutGetId();
        
        // Use reflection to access private getId method
        $reflection = new ReflectionClass($this->linkEntityManager);
        $method = $reflection->getMethod('getId');

        // This should trigger the echo statement for entity without getId method
        $result = $method->invoke($this->linkEntityManager, $entityWithoutId);
        
        $this->assertNull($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testQueryExistingLinkWithInvalidJoinProperties(): void
    {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->linkEntityManager);
        $method = $reflection->getMethod('queryExistingLink');

        $manyToMany = new MtManyToMany(
            mappedBy: User::class,
            joinProperty: null, // Invalid - not a string
            inverseJoinProperty: 'user2'
        );

        // This should trigger the echo statement in queryExistingLink
        $result = $method->invoke($this->linkEntityManager, $manyToMany, 1, 2);
        
        $this->assertNull($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testRemoveFromCollectionPropertyVariousConditions(): void
    {
        // Test with a collection that has the related entity to trigger all echo statements
        $user1 = new User();
        $user1->setId(1);
        $user2 = new User();
        $user2->setId(2);
        
        // Create a collection and add user2 to it
        $collection = new DatabaseCollection([$user2]);
        $user1->setGroups($collection); // This sets the groups property
        
        $entityReflection = new ReflectionClass($user1);
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->linkEntityManager);
        $method = $reflection->getMethod('removeFromCollectionProperty');

        // This should trigger the echo statement when removing the related entity
        $method->invoke($this->linkEntityManager, $user1, $entityReflection, 'groups', $user2);
    }
}