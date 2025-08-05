<?php

namespace MulerTech\Database\Tests\Schema\Diff;

use MulerTech\Database\Schema\Diff\SchemaDifference;
use PHPUnit\Framework\TestCase;

class SchemaDifferenceTest extends TestCase
{
    private SchemaDifference $schemaDifference;
    
    protected function setUp(): void
    {
        $this->schemaDifference = new SchemaDifference();
    }
    
    public function testAddAndGetTablesToCreate(): void
    {
        $this->schemaDifference
            ->addTableToCreate('users', 'App\Entity\User')
            ->addTableToCreate('products', 'App\Entity\Product');
        
        $expected = [
            'users' => 'App\Entity\User',
            'products' => 'App\Entity\Product'
        ];
        
        $this->assertEquals($expected, $this->schemaDifference->getTablesToCreate());
    }
    
    public function testAddAndGetTablesToDrop(): void
    {
        $this->schemaDifference
            ->addTableToDrop('old_users')
            ->addTableToDrop('old_products');
        
        $expected = ['old_users', 'old_products'];
        
        $this->assertEquals($expected, $this->schemaDifference->getTablesToDrop());
    }
    
    public function testAddAndGetColumnsToAdd(): void
    {
        $column1Definition = ['COLUMN_TYPE' => 'VARCHAR(255)', 'IS_NULLABLE' => 'YES'];
        $column2Definition = ['COLUMN_TYPE' => 'INT', 'IS_NULLABLE' => 'NO'];
        
        $this->schemaDifference
            ->addColumnToAdd('users', 'email', $column1Definition)
            ->addColumnToAdd('users', 'age', $column2Definition);
        
        $expected = [
            'users' => [
                'email' => $column1Definition,
                'age' => $column2Definition
            ]
        ];
        
        $this->assertEquals($expected, $this->schemaDifference->getColumnsToAdd());
    }
    
    public function testAddAndGetColumnsToModify(): void
    {
        $column1Diff = ['COLUMN_TYPE' => ['from' => 'VARCHAR(100)', 'to' => 'VARCHAR(255)']];
        $column2Diff = ['IS_NULLABLE' => ['from' => 'NO', 'to' => 'YES']];
        
        $this->schemaDifference
            ->addColumnToModify('users', 'email', $column1Diff)
            ->addColumnToModify('users', 'name', $column2Diff);
        
        $expected = [
            'users' => [
                'email' => $column1Diff,
                'name' => $column2Diff
            ]
        ];
        
        $this->assertEquals($expected, $this->schemaDifference->getColumnsToModify());
    }
    
    public function testAddAndGetColumnsToDrop(): void
    {
        $this->schemaDifference
            ->addColumnToDrop('users', 'old_email')
            ->addColumnToDrop('users', 'old_address')
            ->addColumnToDrop('products', 'old_price');
        
        $expected = [
            'users' => ['old_email', 'old_address'],
            'products' => ['old_price']
        ];
        
        $this->assertEquals($expected, $this->schemaDifference->getColumnsToDrop());
    }
    
    public function testAddAndGetForeignKeysToAdd(): void
    {
        $fk1Definition = [
            'COLUMN_NAME' => 'user_id',
            'REFERENCED_TABLE_NAME' => 'users',
            'REFERENCED_COLUMN_NAME' => 'id'
        ];
        
        $fk2Definition = [
            'COLUMN_NAME' => 'product_id',
            'REFERENCED_TABLE_NAME' => 'products',
            'REFERENCED_COLUMN_NAME' => 'id'
        ];
        
        $this->schemaDifference
            ->addForeignKeyToAdd('orders', 'fk_orders_user', $fk1Definition)
            ->addForeignKeyToAdd('orders', 'fk_orders_product', $fk2Definition);
        
        $expected = [
            'orders' => [
                'fk_orders_user' => $fk1Definition,
                'fk_orders_product' => $fk2Definition
            ]
        ];
        
        $this->assertEquals($expected, $this->schemaDifference->getForeignKeysToAdd());
    }
    
    public function testAddAndGetForeignKeysToDrop(): void
    {
        $this->schemaDifference
            ->addForeignKeyToDrop('orders', 'fk_orders_user')
            ->addForeignKeyToDrop('orders', 'fk_orders_product')
            ->addForeignKeyToDrop('order_items', 'fk_order_items_product');
        
        $expected = [
            'orders' => ['fk_orders_user', 'fk_orders_product'],
            'order_items' => ['fk_order_items_product']
        ];
        
        $this->assertEquals($expected, $this->schemaDifference->getForeignKeysToDrop());
    }
    
    public function testHasDifferencesReturnsTrueWhenDifferencesExist(): void
    {
        // Initially should be false
        $this->assertFalse($this->schemaDifference->hasDifferences());
        
        // Add some differences
        $this->schemaDifference->addTableToCreate('users', 'App\Entity\User');
        $this->assertTrue($this->schemaDifference->hasDifferences());
        
        // Create a new empty instance
        $this->schemaDifference = new SchemaDifference();
        $this->assertFalse($this->schemaDifference->hasDifferences());
        
        // Test each type of difference
        $this->schemaDifference->addTableToDrop('old_table');
        $this->assertTrue($this->schemaDifference->hasDifferences());
        
        $this->schemaDifference = new SchemaDifference();
        $this->schemaDifference->addColumnToAdd('users', 'email', []);
        $this->assertTrue($this->schemaDifference->hasDifferences());
        
        $this->schemaDifference = new SchemaDifference();
        $this->schemaDifference->addColumnToModify('users', 'email', []);
        $this->assertTrue($this->schemaDifference->hasDifferences());
        
        $this->schemaDifference = new SchemaDifference();
        $this->schemaDifference->addColumnToDrop('users', 'old_field');
        $this->assertTrue($this->schemaDifference->hasDifferences());
        
        $this->schemaDifference = new SchemaDifference();
        $this->schemaDifference->addForeignKeyToAdd('orders', 'fk_name', []);
        $this->assertTrue($this->schemaDifference->hasDifferences());
        
        $this->schemaDifference = new SchemaDifference();
        $this->schemaDifference->addForeignKeyToDrop('orders', 'fk_name');
        $this->assertTrue($this->schemaDifference->hasDifferences());
    }
}
