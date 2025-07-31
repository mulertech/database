<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Attributes;

use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(MtOneToOne::class)]
class MtOneToOneTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $relation = new MtOneToOne();
        
        $this->assertNull($relation->targetEntity);
    }

    public function testConstructorWithTargetEntity(): void
    {
        $relation = new MtOneToOne(targetEntity: 'App\\Entity\\UserProfile');
        
        $this->assertEquals('App\\Entity\\UserProfile', $relation->targetEntity);
    }

    public function testAttributeTargetsProperty(): void
    {
        $reflection = new ReflectionClass(MtOneToOne::class);
        $attributes = $reflection->getAttributes();
        
        $this->assertCount(1, $attributes);
        $this->assertEquals(\Attribute::class, $attributes[0]->getName());
        
        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_PROPERTY, $attributeInstance->flags);
    }

    public function testOneToOneWithDifferentEntityTypes(): void
    {
        $profileRelation = new MtOneToOne(targetEntity: 'App\\Entity\\UserProfile');
        $addressRelation = new MtOneToOne(targetEntity: 'App\\Entity\\Address');
        $settingsRelation = new MtOneToOne(targetEntity: 'App\\Entity\\UserSettings');
        
        $this->assertEquals('App\\Entity\\UserProfile', $profileRelation->targetEntity);
        $this->assertEquals('App\\Entity\\Address', $addressRelation->targetEntity);
        $this->assertEquals('App\\Entity\\UserSettings', $settingsRelation->targetEntity);
    }

    public function testOneToOneWithComplexNamespace(): void
    {
        $relation = new MtOneToOne(
            targetEntity: 'Very\\Long\\Namespace\\Path\\To\\RelatedEntity'
        );
        
        $this->assertEquals('Very\\Long\\Namespace\\Path\\To\\RelatedEntity', $relation->targetEntity);
    }

    public function testOneToOneWithTestEntity(): void
    {
        $relation = new MtOneToOne(
            targetEntity: 'MulerTech\\Database\\Tests\\Entity\\TestRelatedEntity'
        );
        
        $this->assertEquals('MulerTech\\Database\\Tests\\Entity\\TestRelatedEntity', $relation->targetEntity);
    }

    public function testMultipleOneToOneRelations(): void
    {
        $relations = [
            new MtOneToOne(targetEntity: 'App\\Entity\\Passport'),
            new MtOneToOne(targetEntity: 'App\\Entity\\License'),
            new MtOneToOne(targetEntity: 'App\\Entity\\Certificate'),
        ];
        
        $this->assertEquals('App\\Entity\\Passport', $relations[0]->targetEntity);
        $this->assertEquals('App\\Entity\\License', $relations[1]->targetEntity);
        $this->assertEquals('App\\Entity\\Certificate', $relations[2]->targetEntity);
    }

    public function testOneToOneWithNullTargetEntity(): void
    {
        $relation = new MtOneToOne(targetEntity: null);
        
        $this->assertNull($relation->targetEntity);
    }

    public function testOneToOneConstructorParameterHandling(): void
    {
        // Test with explicit null
        $relation1 = new MtOneToOne(targetEntity: null);
        $this->assertNull($relation1->targetEntity);
        
        // Test with no parameters (default)
        $relation2 = new MtOneToOne();
        $this->assertNull($relation2->targetEntity);
        
        // Test with entity
        $relation3 = new MtOneToOne(targetEntity: 'Test\\Entity');
        $this->assertEquals('Test\\Entity', $relation3->targetEntity);
    }

    public function testOneToOneRelationshipExamples(): void
    {
        // User -> UserProfile relationship
        $userProfileRelation = new MtOneToOne(targetEntity: 'App\\Entity\\UserProfile');
        $this->assertEquals('App\\Entity\\UserProfile', $userProfileRelation->targetEntity);
        
        // Company -> CompanyDetails relationship
        $companyDetailsRelation = new MtOneToOne(targetEntity: 'App\\Entity\\CompanyDetails');
        $this->assertEquals('App\\Entity\\CompanyDetails', $companyDetailsRelation->targetEntity);
        
        // Product -> ProductSpecification relationship
        $productSpecRelation = new MtOneToOne(targetEntity: 'App\\Entity\\ProductSpecification');
        $this->assertEquals('App\\Entity\\ProductSpecification', $productSpecRelation->targetEntity);
    }

    public function testOneToOneWithBidirectionalRelationship(): void
    {
        // Forward relationship
        $forwardRelation = new MtOneToOne(targetEntity: 'App\\Entity\\Invoice');
        $this->assertEquals('App\\Entity\\Invoice', $forwardRelation->targetEntity);
        
        // Inverse relationship (would be on the Invoice entity)
        $inverseRelation = new MtOneToOne(targetEntity: 'App\\Entity\\Order');
        $this->assertEquals('App\\Entity\\Order', $inverseRelation->targetEntity);
    }
}