<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\StateValidator;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

class StateValidatorTest extends TestCase
{
    private StateValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->validator = new StateValidator();
    }

    public function testValidatePersistWithNewState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'persist');
        
        $this->assertTrue($result);
    }

    public function testValidatePersistWithDetachedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::DETACHED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'persist');
        
        $this->assertTrue($result);
    }

    public function testValidatePersistWithManagedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'persist');
        
        $this->assertFalse($result);
    }

    public function testValidatePersistWithRemovedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'persist');
        
        $this->assertFalse($result);
    }

    public function testValidateUpdateWithManagedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'update');
        
        $this->assertTrue($result);
    }

    public function testValidateUpdateWithNewState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'update');
        
        $this->assertFalse($result);
    }

    public function testValidateUpdateWithDetachedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::DETACHED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'update');
        
        $this->assertFalse($result);
    }

    public function testValidateUpdateWithRemovedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'update');
        
        $this->assertFalse($result);
    }

    public function testValidateRemoveWithManagedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'remove');
        
        $this->assertTrue($result);
    }

    public function testValidateRemoveWithNewState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'remove');
        
        $this->assertTrue($result);
    }

    public function testValidateRemoveWithDetachedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::DETACHED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'remove');
        
        $this->assertTrue($result);
    }

    public function testValidateRemoveWithRemovedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'remove');
        
        $this->assertFalse($result);
    }

    public function testValidateMergeWithDetachedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::DETACHED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'merge');
        
        $this->assertTrue($result);
    }

    public function testValidateMergeWithManagedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'merge');
        
        $this->assertFalse($result);
    }

    public function testValidateMergeWithNewState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'merge');
        
        $this->assertFalse($result);
    }

    public function testValidateMergeWithRemovedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'merge');
        
        $this->assertFalse($result);
    }

    public function testValidateDetachWithManagedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'detach');
        
        $this->assertTrue($result);
    }

    public function testValidateDetachWithNewState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'detach');
        
        $this->assertTrue($result);
    }

    public function testValidateDetachWithDetachedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::DETACHED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'detach');
        
        $this->assertFalse($result);
    }

    public function testValidateDetachWithRemovedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'detach');
        
        $this->assertFalse($result);
    }

    public function testValidateRefreshWithManagedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'refresh');
        
        $this->assertTrue($result);
    }

    public function testValidateRefreshWithNewState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'refresh');
        
        $this->assertFalse($result);
    }

    public function testValidateRefreshWithDetachedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::DETACHED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'refresh');
        
        $this->assertFalse($result);
    }

    public function testValidateRefreshWithRemovedState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'refresh');
        
        $this->assertFalse($result);
    }

    public function testValidateUnknownOperationInStrictMode(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'unknown_operation');
        
        // Should return false in strict mode for unknown operations
        $this->assertFalse($result);
    }

    public function testValidateOperationWithEntityHavingGetId(): void
    {
        $entity = new User(); // User has getId() method
        $currentState = EntityLifecycleState::NEW;
        
        $result = $this->validator->validateOperation($entity, $currentState, 'persist');
        
        $this->assertTrue($result);
    }

    public function testAllOperationsWorkWithValidStates(): void
    {
        $entity = new User();
        
        // Test all operation-state combinations that should be valid
        $validCombinations = [
            ['persist', EntityLifecycleState::NEW],
            ['persist', EntityLifecycleState::DETACHED],
            ['update', EntityLifecycleState::MANAGED],
            ['remove', EntityLifecycleState::MANAGED],
            ['remove', EntityLifecycleState::NEW],
            ['remove', EntityLifecycleState::DETACHED],
            ['merge', EntityLifecycleState::DETACHED],
            ['detach', EntityLifecycleState::MANAGED],
            ['detach', EntityLifecycleState::NEW],
            ['refresh', EntityLifecycleState::MANAGED],
        ];
        
        foreach ($validCombinations as [$operation, $state]) {
            $result = $this->validator->validateOperation($entity, $state, $operation);
            $this->assertTrue($result, "Operation '$operation' should be valid for state '{$state->value}'");
        }
    }

    public function testAllOperationsFailWithInvalidStates(): void
    {
        $entity = new User();
        
        // Test some operation-state combinations that should be invalid
        $invalidCombinations = [
            ['persist', EntityLifecycleState::MANAGED],
            ['persist', EntityLifecycleState::REMOVED],
            ['update', EntityLifecycleState::NEW],
            ['update', EntityLifecycleState::DETACHED],
            ['update', EntityLifecycleState::REMOVED],
            ['remove', EntityLifecycleState::REMOVED],
            ['merge', EntityLifecycleState::MANAGED],
            ['merge', EntityLifecycleState::NEW],
            ['merge', EntityLifecycleState::REMOVED],
            ['detach', EntityLifecycleState::DETACHED],
            ['detach', EntityLifecycleState::REMOVED],
            ['refresh', EntityLifecycleState::NEW],
            ['refresh', EntityLifecycleState::DETACHED],
            ['refresh', EntityLifecycleState::REMOVED],
        ];
        
        foreach ($invalidCombinations as [$operation, $state]) {
            $result = $this->validator->validateOperation($entity, $state, $operation);
            $this->assertFalse($result, "Operation '$operation' should be invalid for state '{$state->value}'");
        }
    }
}