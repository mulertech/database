<?php

namespace MulerTech\Database\Tests\Relational;

use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Query\Clause\ComparisonOperator;
use MulerTech\Database\Query\Types\LinkOperator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class QueryBuilderTest extends TestCase
{
    public function testQueryBuilderInsertOneNameParameter(): void
    {
        $queryBuilder = new QueryBuilder()
            ->insert('atable')
            ->set('column1', 'data column 1');
        self::assertEquals('INSERT INTO `atable` (`column1`) VALUES (:param0)', $queryBuilder->toSql());
    }

    public function testQueryBuilderGetExecuteNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder()
            ->insert('atable')
            ->set('column1', 'data column 1')
            ->set('column2', 'data column 2');
        self::assertEquals(
            [':param0' => 'data column 1', ':param1' => 'data column 2'],
            $queryBuilder->getParameterBag()->getNamedValues()
        );
    }

    public function testQueryBuilderGetBindNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $insertBuilder = $queryBuilder->insert('atable')
            ->set('column1', 'data column 1')
            ->set('column2', 'data column 2', PDO::PARAM_INT);

        $parameters = $insertBuilder->getParameterBag()->toArray();
        self::assertCount(2, $parameters);
        self::assertEquals('data column 1', $parameters[':param0']['value']);
        self::assertEquals(PDO::PARAM_STR, $parameters[':param0']['type']);
        self::assertEquals('data column 2', $parameters[':param1']['value']);
        self::assertEquals(PDO::PARAM_INT, $parameters[':param1']['type']);
    }

    public function testQueryBuilderNullGetBindParameters(): void
    {
        $queryBuilder = new QueryBuilder();
        $insertBuilder = $queryBuilder->insert('atable');

        $parameters = $insertBuilder->getParameterBag()->toArray();
        self::assertEmpty($parameters['named']);
        self::assertEmpty($parameters['positional']);
    }

    public function testQueryBuilderInsertNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $insertBuilder = $queryBuilder->insert('atable')
            ->set('column1', 'data column 1')
            ->set('column2', 'data column 2');

        self::assertEquals(
            'INSERT INTO `atable` (`column1`, `column2`) VALUES (:param0, :param1)',
            $insertBuilder->toSql()
        );
    }

    public function testQueryBuilderInsertAllNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $insertBuilder = $queryBuilder->insert('atable')
            ->set('column1', 'data column 1')
            ->set('column2', 'data column 2');

        self::assertEquals(
            'INSERT INTO `atable` (`column1`, `column2`) VALUES (:param0, :param1)',
            $insertBuilder->toSql()
        );
    }

    public function testQueryBuilderWithoutSetValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No SET values specified for UPDATE');

        // Créer un UpdateBuilder sans spécifier de table
        $queryBuilder = new QueryBuilder();
        $updateBuilder = $queryBuilder->update('test');
        $updateBuilder->toSql();
    }

    public function testQueryBuilderSimpleSelect(): void
    {
        $queryBuilder = new QueryBuilder();
        $selectBuilder = $queryBuilder->select('*')->from('atable');
        self::assertEquals('SELECT * FROM `atable`', $selectBuilder->toSql());
    }

    public function testQueryBuilderSelectMultipleFrom(): void
    {
        $queryBuilder = new QueryBuilder();
        $selectBuilder = $queryBuilder->select('name', 'price', 'options', 'photo')
            ->from('food')
            ->from('food_menu')
            ->where('food_id', 1);

        self::assertEquals(
            'SELECT `name`, `price`, `options`, `photo` FROM `food`, `food_menu` WHERE `food_id` = :param0',
            $selectBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectMultipleFromWithAlias(): void
    {
        $selectBuilder = new QueryBuilder()
            ->select('name', 'price', 'options', 'photo')
            ->from('food', 'foodas')
            ->from('food_menu', 'menuas')
            ->where('food_id', 2);

        self::assertEquals(
            'SELECT `name`, `price`, `options`, `photo` FROM `food` AS `foodas`, `food_menu` AS `menuas` WHERE `food_id` = :param0',
            $selectBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectWithFromSubQuery(): void
    {
        $subQuery = new QueryBuilder()
            ->select('c1 sc1', 'c2 sc2', 'c3 sc3')
            ->from('tb1');

        $queryBuilder = new QueryBuilder()
            ->select('sc1', 'sc2', 'sc3')
            ->from($subQuery, 'sb')
            ->where('sc1', 1, ComparisonOperator::GREATER_THAN);

        self::assertEquals(
            'SELECT `sc1`, `sc2`, `sc3` FROM (SELECT `c1` AS `sc1`, `c2` AS `sc2`, `c3` AS `sc3` FROM `tb1`) AS `sb` WHERE `sc1` > :param0',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectLimit(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('*')
            ->from('atable')
            ->limit(10)
            ->offset(40);

        self::assertEquals('SELECT * FROM `atable` LIMIT 10 OFFSET 40', $queryBuilder->toSql());
    }

    public function testQueryBuilderSelectWithoutLimit(): void
    {
        $this->expectExceptionMessage('Cannot set offset without a limit.');
        new QueryBuilder()->select('*')->from('atable')->offset(5);
    }

    public function testQueryBuilderSelectLimitManualOffset(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('*')
            ->from('atable')
            ->limit(10)
            ->offset(40);

        self::assertEquals('SELECT * FROM `atable` LIMIT 10 OFFSET 40', $queryBuilder->toSql());
    }

    public function testQueryBuilderSelectLimitWithPage(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('*')
            ->from('atable')
            ->limit(10)
            ->offset(null, 3);

        self::assertEquals('SELECT * FROM `atable` LIMIT 10 OFFSET 20', $queryBuilder->toSql());
    }

    public function testQueryBuilderSelectDistinct(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('firstname')
            ->from('atable')
            ->distinct();

        self::assertEquals('SELECT DISTINCT `firstname` FROM `atable`', $queryBuilder->toSql());
    }

    public function testQueryBuilderSelect(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable')
            ->orderBy('age', 'DESC')
            ->orderBy('username');

        self::assertEquals(
            'SELECT `username` AS `name`, `age` AS `user_age` FROM `atable` ORDER BY `age` DESC, `username` ASC',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectInnerJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('customername', 'customercity', 'customermail', 'ordertotal', 'salestotal')
            ->from('customers', 'c')
            ->innerJoin('orders', 'c.customerid', 'o.customerid', 'o')
            ->leftJoin('sales', 'o.orderid', 's.orderid', 's');

        self::assertEquals(
            'SELECT `customername`, `customercity`, `customermail`, `ordertotal`, `salestotal` FROM `customers` AS `c` INNER JOIN `orders` AS `o` ON `c`.`customerid` = `o`.`customerid` LEFT JOIN `sales` AS `s` ON `o`.`orderid` = `s`.`orderid`',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectLeftJoinInformationSchema(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select(
                'k.TABLE_NAME',
                'k.CONSTRAINT_NAME',
                'k.COLUMN_NAME',
                'k.REFERENCED_TABLE_SCHEMA',
                'k.REFERENCED_TABLE_NAME',
                'k.REFERENCED_COLUMN_NAME',
                'r.DELETE_RULE',
                'r.UPDATE_RULE'
            )
            ->from('INFORMATION_SCHEMA.KEY_COLUMN_USAGE', 'k')
            ->leftJoin('INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS', 'k.CONSTRAINT_NAME', 'r.CONSTRAINT_NAME', 'r')
            ->where('k.CONSTRAINT_SCHEMA', 'db')
            ->whereNotNull('k.REFERENCED_TABLE_SCHEMA')
            ->whereNotNull('k.REFERENCED_TABLE_NAME')
            ->whereNotNull('k.REFERENCED_COLUMN_NAME');

        self::assertEquals(
            'SELECT `k`.`TABLE_NAME`, `k`.`CONSTRAINT_NAME`, `k`.`COLUMN_NAME`, `k`.`REFERENCED_TABLE_SCHEMA`, `k`.`REFERENCED_TABLE_NAME`, `k`.`REFERENCED_COLUMN_NAME`, `r`.`DELETE_RULE`, `r`.`UPDATE_RULE` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` AS `k` LEFT JOIN `INFORMATION_SCHEMA`.`REFERENTIAL_CONSTRAINTS` AS `r` ON `k`.`CONSTRAINT_NAME` = `r`.`CONSTRAINT_NAME` WHERE `k`.`CONSTRAINT_SCHEMA` = :param0 AND `k`.`REFERENCED_TABLE_SCHEMA` IS NOT NULL AND `k`.`REFERENCED_TABLE_NAME` IS NOT NULL AND `k`.`REFERENCED_COLUMN_NAME` IS NOT NULL',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectCrossJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable', 'table')
            ->crossJoin('btable');

        self::assertEquals(
            'SELECT `username` AS `name`, `age` AS `user_age` FROM `atable` AS `table` CROSS JOIN `btable`',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectRightJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable', 'table')
            ->rightJoin('btable', 'table.id_btable', 'btable.id');

        self::assertEquals(
            'SELECT `username` AS `name`, `age` AS `user_age` FROM `atable` AS `table` RIGHT JOIN `btable` ON `table`.`id_btable` = `btable`.`id`',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectGroupBy(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('year', 'SUM(profit) AS profit')
            ->from('sales')
            ->groupBy('year');

        self::assertEquals(
            'SELECT `year`, sum(profit) AS `profit` FROM `sales` GROUP BY `year`',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where('pr.numprod', 'p.numprod')
            ->where('pr.catprod', 'category', ComparisonOperator::EQUAL, LinkOperator::AND);

        self::assertEquals(
            'SELECT COUNT(*) FROM `products` AS `pr` WHERE `pr`.`numprod` = :param0 AND `pr`.`catprod` = :param1',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectOrWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where('pr.numprod', 'p.numprod')
            ->where('pr.catprod', 'category', ComparisonOperator::EQUAL, LinkOperator::OR);

        self::assertEquals(
            'SELECT COUNT(*) FROM `products` AS `pr` WHERE `pr`.`numprod` = :param0 OR `pr`.`catprod` = :param1',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectManualWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->whereRaw('pr.numprod=p.numprod');

        self::assertEquals(
            'SELECT COUNT(*) FROM `products` AS `pr` WHERE pr.numprod=p.numprod',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderSelectHavingAnd(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select(
                'ordernumber',
                'SUM(quantityOrdered) AS itemsCount',
                'SUM(priceeach*quantityOrdered) AS total'
            )
            ->from('orderdetails')
            ->groupBy('ordernumber')
            ->having('total', 1000, ComparisonOperator::GREATER_THAN)
            ->having('itemsCount', 600, ComparisonOperator::GREATER_THAN)
            ->having('total', 10000, ComparisonOperator::LESS_THAN);

        self::assertEquals(
            'SELECT `ordernumber`, sum(quantityordered) AS `itemscount`, sum(priceeach*quantityordered) AS `total` FROM `orderdetails` GROUP BY `ordernumber` HAVING `total` > :param0 AND `itemsCount` > :param1 AND `total` < :param2',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderUpdate(): void
    {
        $queryBuilder = new QueryBuilder()
            ->update('employees')
            ->set('lastname', 'Hill')
            ->set('firstname', 'John');

        self::assertEquals(
            'UPDATE `employees` SET `lastname` = :param0, `firstname` = :param1',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderUpdateAlias(): void
    {
        $queryBuilder = new QueryBuilder()
            ->update('employees', 'emp')
            ->set('lastname', 'Hill');

        self::assertEquals(
            'UPDATE `employees` AS `emp` SET `lastname` = :param0',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderUpdateWithWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->update('employees')
            ->set('lastname', 'Hill')
            ->where('id', 33806);

        self::assertEquals(
            'UPDATE `employees` SET `lastname` = :param0 WHERE `id` = :param1',
            $queryBuilder->toSql()
        );
    }

    public function testQueryBuilderDelete(): void
    {
        $queryBuilder = new QueryBuilder()
            ->delete('employees')
            ->where('employees.id', 1);

        self::assertEquals(
            'DELETE FROM `employees` WHERE `employees`.`id` = :param0',
            $queryBuilder->toSql()
        );
    }

    public function testWhereInClause(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('*')
            ->from('employees')
            ->whereIn('id', [1, 2, 3]);

        self::assertEquals(
            'SELECT * FROM `employees` WHERE `id` IN (:param0, :param1, :param2)',
            $queryBuilder->toSql()
        );
    }

    public function testWhereBetweenClause(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('*')
            ->from('employees')
            ->whereBetween('salary', 1000, 5000);

        self::assertEquals(
            'SELECT * FROM `employees` WHERE `salary` BETWEEN :param0 AND :param1',
            $queryBuilder->toSql()
        );
    }
}

