<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Exception;

use MulerTech\Database\ORM\Exception\HydrationException;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use Exception;

class HydrationExceptionTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $exception = new HydrationException('Test hydration error');
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertEquals('Test hydration error', $exception->getMessage());
        self::assertEquals(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function testConstructionWithCode(): void
    {
        $exception = new HydrationException('Test hydration error', 500);
        
        self::assertEquals('Test hydration error', $exception->getMessage());
        self::assertEquals(500, $exception->getCode());
    }

    public function testConstructionWithPrevious(): void
    {
        $previous = new Exception('Previous error');
        $exception = new HydrationException('Test hydration error', 0, $previous);
        
        self::assertEquals('Test hydration error', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testForInvalidProperty(): void
    {
        $exception = HydrationException::forInvalidProperty(User::class, 'nonExistentProperty');
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertStringContainsString(User::class, $exception->getMessage());
        self::assertStringContainsString('nonExistentProperty', $exception->getMessage());
        self::assertStringContainsString('Invalid property', $exception->getMessage());
    }

    public function testForTypeError(): void
    {
        $exception = HydrationException::forTypeError(User::class, 'username', 'string', 'integer');
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertStringContainsString(User::class, $exception->getMessage());
        self::assertStringContainsString('username', $exception->getMessage());
        self::assertStringContainsString('string', $exception->getMessage());
        self::assertStringContainsString('integer', $exception->getMessage());
        self::assertStringContainsString('Type error', $exception->getMessage());
    }

    public function testForMissingData(): void
    {
        $exception = HydrationException::forMissingData(User::class, ['id', 'username']);
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertStringContainsString(User::class, $exception->getMessage());
        self::assertStringContainsString('id', $exception->getMessage());
        self::assertStringContainsString('username', $exception->getMessage());
        self::assertStringContainsString('Missing required data', $exception->getMessage());
    }

    public function testForHydrationFailure(): void
    {
        $previous = new Exception('Database error');
        $exception = HydrationException::forHydrationFailure(User::class, 'Failed to hydrate', $previous);
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertStringContainsString(User::class, $exception->getMessage());
        self::assertStringContainsString('Failed to hydrate', $exception->getMessage());
        self::assertStringContainsString('Hydration failure', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testForInvalidEntity(): void
    {
        $exception = HydrationException::forInvalidEntity('NotAnEntity');
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertStringContainsString('NotAnEntity', $exception->getMessage());
        self::assertStringContainsString('Invalid entity class', $exception->getMessage());
    }

    public function testInheritanceFromException(): void
    {
        $exception = new HydrationException('Test');
        
        self::assertInstanceOf(Exception::class, $exception);
        self::assertInstanceOf(\Throwable::class, $exception);
    }

    public function testStackTrace(): void
    {
        try {
            throw new HydrationException('Test hydration error');
        } catch (HydrationException $e) {
            $trace = $e->getTrace();
            self::assertIsArray($trace);
            self::assertNotEmpty($trace);
            self::assertArrayHasKey('file', $trace[0]);
            self::assertArrayHasKey('line', $trace[0]);
        }
    }

    public function testToString(): void
    {
        $exception = new HydrationException('Test hydration error', 123);
        $string = (string) $exception;
        
        self::assertStringContainsString('HydrationException', $string);
        self::assertStringContainsString('Test hydration error', $string);
        self::assertStringContainsString('123', $string);
    }

    public function testWithComplexMessage(): void
    {
        $message = "Failed to hydrate entity 'User' with data: {\"id\": 1, \"username\": \"john\"}";
        $exception = new HydrationException($message);
        
        self::assertEquals($message, $exception->getMessage());
    }

    public function testForRelationHydrationError(): void
    {
        $previous = new Exception('Relation not found');
        $exception = HydrationException::forHydrationFailure(
            User::class,
            'Failed to hydrate relation "unit"',
            $previous
        );
        
        self::assertStringContainsString('relation', $exception->getMessage());
        self::assertStringContainsString('unit', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testFailedToHydrateEntity(): void
    {
        $exception = HydrationException::failedToHydrateEntity(User::class);
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertStringContainsString(User::class, $exception->getMessage());
        self::assertStringContainsString('Failed to hydrate entity', $exception->getMessage());
        self::assertEquals(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function testFailedToHydrateEntityWithPrevious(): void
    {
        $previous = new Exception('Database connection error');
        $exception = HydrationException::failedToHydrateEntity(User::class, $previous);
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertStringContainsString(User::class, $exception->getMessage());
        self::assertStringContainsString('Failed to hydrate entity', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testPropertyCannotBeNull(): void
    {
        $exception = HydrationException::propertyCannotBeNull('username', User::class);
        
        self::assertInstanceOf(HydrationException::class, $exception);
        self::assertStringContainsString('username', $exception->getMessage());
        self::assertStringContainsString(User::class, $exception->getMessage());
        self::assertStringContainsString('cannot be null', $exception->getMessage());
        self::assertEquals(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }
}