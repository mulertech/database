<?php

namespace MulerTech\Database\Tests\Migration;

use MulerTech\Database\Migration\Migration;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Migrations\Migration202504211358;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Migration $migration;
    
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        // Create concrete implementation of abstract Migration class
        $this->migration = new Migration202504211358($this->entityManager);
    }
    
    public function testConstructorInitializesMetadataWithSpecificNamingPattern(): void
    {
        $this->assertEquals('20250421-1358', $this->migration->getVersion());
    }
}
