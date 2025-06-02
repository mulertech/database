<?php

namespace MulerTech\Database\Tests\Relational;

use MulerTech\Database\Query\AbstractQueryBuilder;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use PDO;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    public function testQueryBuilderInsertOneNameParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->insert('atable')
            ->setValue('column1', 'data column 1');
        self::assertEquals('INSERT INTO `atable` (`column1`) VALUES (:namedParam1)', $queryBuilder->getQuery());
    }

    public function testQueryBuilderGetExecuteNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', 'data column 1');
        $queryBuilder->setValue('column2', 'data column 2');
        self::assertEquals(
            [':namedParam1' => ['data column 1', 2], ':namedParam2' => ['data column 2', 2]],
            $queryBuilder->getNamedParameters()
        );
    }

    public function testQueryBuilderGetBindNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', 'data column 1');
        $queryBuilder->setValue('column2', 'data column 2', PDO::PARAM_INT);
        self::assertEquals(
            [[':namedParam1', 'data column 1', 2], [':namedParam2', 'data column 2', 1]],
            $queryBuilder->getCurrentBuilder()->getBindParameters()
        );
    }

    public function testQueryBuilderNullGetBindParameters(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        self::assertEquals(null, $queryBuilder->getCurrentBuilder()->getBindParameters());
    }

    public function testQueryBuilderInsertNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', 'data column 1');
        $queryBuilder->setValue('column2', 'data column 2');
        self::assertEquals(
            'INSERT INTO `atable` (`column1`, `column2`) VALUES (:namedParam1, :namedParam2)',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderInsertAllNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValues([
            ['column1', 'data column 1'],
            ['column2', 'data column 2']
        ]);
        self::assertEquals(
            'INSERT INTO `atable` (`column1`, `column2`) VALUES (:namedParam1, :namedParam2)',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderWithNoType(): void
    {
        $this->expectExceptionMessage(
            'No query built yet'
        );
        new QueryBuilder()->from('atable')->getQuery();
    }

    public function testQueryBuilderSimpleSelect(): void
    {
        $queryBuilder = new QueryBuilder()->select('*')->from('atable');
        self::assertEquals('SELECT * FROM `atable`', $queryBuilder->getQuery());
    }

    public function testQueryBuilderSelectMultipleFrom(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('name', 'price', 'options', 'photo')
            ->from('food')->from('food_menu')
            ->where(SqlOperations::equal('food_id', 1));
        self::assertEquals(
            'SELECT `name`, `price`, `options`, `photo` FROM `food`, `food_menu` WHERE food_id=1',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectMultipleFromWithAlias(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('name', 'price', 'options', 'photo')
            ->from('food', 'foodas')->from('food_menu', 'menuas')
            ->where(SqlOperations::equal('food_id', 1));
        self::assertEquals(
            'SELECT `name`, `price`, `options`, `photo` FROM `food` AS foodas, `food_menu` AS menuas WHERE food_id=1',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectWithFromSubQuery(): void
    {
        $from = new QueryBuilder();
        $from->select('c1 sc1', 'c2 sc2', 'c3 sc3')->from('tb1');
        $queryBuilder = new QueryBuilder()
            ->select('sc1', 'sc2', 'sc3')
            ->from($from, 'sb')
            ->where(SqlOperations::greater('sc1', 1));
        self::assertEquals(
            'SELECT `sc1`, `sc2`, `sc3` FROM (SELECT `c1` AS sc1, `c2` AS sc2, `c3` AS sc3 FROM `tb1`) AS sb WHERE sc1>1',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectWithFromSubQuerySelectAlias(): void
    {
        $from = new QueryBuilder()->select('c1 sc1', 'c2 sc2', 'c3 sc3')->from('tb1')->alias('sb');
        $queryBuilder = new QueryBuilder()
            ->select('sc1', 'sc2', 'sc3')
            ->from($from)
            ->where(SqlOperations::greater('sc1', 1));
        self::assertEquals(
            'SELECT `sc1`, `sc2`, `sc3` FROM (SELECT `c1` AS sc1, `c2` AS sc2, `c3` AS sc3 FROM `tb1`) AS sb WHERE sc1>1',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectLimit(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('*')
            ->from('atable')
            ->limit(10)
            ->offset(5, 10); // 5 is the page for offset calcul, 10 is not used
        self::assertEquals('SELECT * FROM `atable` LIMIT 10 OFFSET 40', $queryBuilder->getQuery());
    }

    public function testQueryBuilderSelectWithoutLimit(): void
    {
        $this->expectExceptionMessage(
            'Cannot set offset without a limit.'
        );
        new QueryBuilder()->select('*')->from('atable')->offset(5, 10);
    }

    public function testQueryBuilderSelectLimitManualOffset(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('*')
            ->from('atable')
            ->limit(10)
            ->offset(null, 40);
        self::assertEquals('SELECT * FROM `atable` LIMIT 10 OFFSET 40', $queryBuilder->getQuery());
    }

    public function testQueryBuilderSelectDistinct(): void
    {
        $queryBuilder = new QueryBuilder()->select('firstname')->from('atable')->distinct();
        self::assertEquals('SELECT DISTINCT `firstname` FROM `atable`', $queryBuilder->getQuery());
    }

    public function testQueryBuilderSelectDistinctIndicated(): void
    {
        $queryBuilder = new QueryBuilder()->select('DISTINCT firstname')->from('atable')->distinct();
        self::assertEquals('SELECT DISTINCT `firstname` FROM `atable`', $queryBuilder->getQuery());
    }

    public function testQueryBuilderSelect(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable')
            ->orderBy('age', 'DESC')
            ->addOrderBy('username');
        self::assertEquals(
            'SELECT `username` AS name, `age` as user_age FROM `atable` ORDER BY `age` DESC, `username` ASC',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectInnerJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('customername', 'customercity', 'customermail', 'ordertotal', 'salestotal')
            ->from('customers c')
            ->innerJoin('orders o', 'c.customerid=o.customerid')
            ->leftJoin('sales s', 'o.orderid=s.orderid');
        self::assertEquals(
            'SELECT `customername`, `customercity`, `customermail`, `ordertotal`, `salestotal` FROM `customers` AS c INNER JOIN `orders` AS o ON `c`.`customerid`=`o`.`customerid` LEFT JOIN `sales` AS s ON `o`.`orderid`=`s`.`orderid`',
            $queryBuilder->getQuery()
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
            ->leftJoin(
                'INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r',
                'k.CONSTRAINT_NAME=r.CONSTRAINT_NAME'
            )
            ->where(SqlOperations::equal('k.CONSTRAINT_SCHEMA', "'db'"))
            ->andWhere('k.REFERENCED_TABLE_SCHEMA IS NOT NULL')
            ->andWhere('k.REFERENCED_TABLE_NAME IS NOT NULL')
            ->andWhere('k.REFERENCED_COLUMN_NAME IS NOT NULL');
        self::assertEquals(
            'SELECT `k`.`TABLE_NAME`, `k`.`CONSTRAINT_NAME`, `k`.`COLUMN_NAME`, `k`.`REFERENCED_TABLE_SCHEMA`, `k`.`REFERENCED_TABLE_NAME`, `k`.`REFERENCED_COLUMN_NAME`, `r`.`DELETE_RULE`, `r`.`UPDATE_RULE` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` AS k LEFT JOIN `INFORMATION_SCHEMA`.`REFERENTIAL_CONSTRAINTS` AS r ON `k`.`CONSTRAINT_NAME`=`r`.`CONSTRAINT_NAME` WHERE k.CONSTRAINT_SCHEMA=\'db\' AND k.REFERENCED_TABLE_SCHEMA IS NOT NULL AND k.REFERENCED_TABLE_NAME IS NOT NULL AND k.REFERENCED_COLUMN_NAME IS NOT NULL',
            $queryBuilder->getQuery()
        );
    }
    public function testQueryBuilderSelectComplexLeftJoinThatFollow(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select(
                'basecolumn',
                'acolumn',
                'bcolumn',
                'ccolumn',
                'dcolumn',
                'ecolumn',
                'fcolumn',
                'gcolumn',
                'hcolumn',
                'icolumn',
                'jcolumn'
            )
            ->from('basetable')
            ->leftJoin('atable a', 'basetable.aid=a.tid')
            ->leftJoin('btable b', 'a.bid=b.aid')
            ->leftJoin('ctable c', 'b.cid=c.bid')
            ->leftJoin('dtable d', 'c.did=d.cid')
            ->leftJoin('etable e', 'd.eid=e.did')
            ->leftJoin('ftable f', 'e.fid=f.eid')
            ->leftJoin('gtable', 'f.gid=gtable.fid')
            ->leftJoin('htable', 'gtable.hid=htable.gid')
            ->leftJoin('itable', 'htable.iid=itable.hid')
            ->leftJoin('jtable', 'itable.jid=jtable.iid');
        self::assertEquals(
            'SELECT `basecolumn`, `acolumn`, `bcolumn`, `ccolumn`, `dcolumn`, `ecolumn`, `fcolumn`, `gcolumn`, `hcolumn`, `icolumn`, `jcolumn` FROM `basetable` LEFT JOIN `atable` AS a ON `basetable`.`aid`=`a`.`tid` LEFT JOIN `btable` AS b ON `a`.`bid`=`b`.`aid` LEFT JOIN `ctable` AS c ON `b`.`cid`=`c`.`bid` LEFT JOIN `dtable` AS d ON `c`.`did`=`d`.`cid` LEFT JOIN `etable` AS e ON `d`.`eid`=`e`.`did` LEFT JOIN `ftable` AS f ON `e`.`fid`=`f`.`eid` LEFT JOIN `gtable` ON `f`.`gid`=`gtable`.`fid` LEFT JOIN `htable` ON `gtable`.`hid`=`htable`.`gid` LEFT JOIN `itable` ON `htable`.`iid`=`itable`.`hid` LEFT JOIN `jtable` ON `itable`.`jid`=`jtable`.`iid`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectComplexLeftJoinMultipheTableAlias(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select(
                'basecolumn',
                'alphacolumn',
                'bravocolumn',
                'charliecolumn',
                'bcolumn'
            )
            ->from('basetable t')
            ->leftJoin('atable alpha', 't.aid=alpha.tid')
            ->leftJoin('atable bravo', 't.bid=bravo.tid')
            ->leftJoin('atable charlie', 't.cid=charlie.tid')
            ->leftJoin('btable', 'alpha.btableid=btable.alphaid');
        self::assertEquals(
            'SELECT `basecolumn`, `alphacolumn`, `bravocolumn`, `charliecolumn`, `bcolumn` FROM `basetable` AS t LEFT JOIN `atable` AS alpha ON `t`.`aid`=`alpha`.`tid` LEFT JOIN `atable` AS bravo ON `t`.`bid`=`bravo`.`tid` LEFT JOIN `atable` AS charlie ON `t`.`cid`=`charlie`.`tid` LEFT JOIN `btable` ON `alpha`.`btableid`=`btable`.`alphaid`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectComplexLeftJoinThatNotFollow(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select(
                'basecolumn',
                'acolumn',
                'bcolumn',
                'ccolumn',
                'dcolumn',
                'ecolumn',
                'fcolumn',
                'gcolumn',
                'hcolumn',
                'icolumn',
                'jcolumn'
            )
            ->from('basetable t')
            ->leftJoin('atable a', 't.aid=a.tid')
            ->leftJoin('btable b', 't.bid=b.tid')
            ->leftJoin('ctable c', 't.cid=c.tid')
            ->leftJoin('dtable d', 'a.did=d.aid')
            ->leftJoin('etable e', 'b.eid=e.bid')
            ->leftJoin('ftable f', 'b.fid=f.bid')
            ->leftJoin('gtable g', 'd.gid=g.did')
            ->leftJoin('htable h', 'e.hid=h.eid')
            ->leftJoin('itable i', 'g.iid=i.gid')
            ->leftJoin('jtable j', 'g.jid=j.gid');
        self::assertEquals(
            'SELECT `basecolumn`, `acolumn`, `bcolumn`, `ccolumn`, `dcolumn`, `ecolumn`, `fcolumn`, `gcolumn`, `hcolumn`, `icolumn`, `jcolumn` FROM `basetable` AS t LEFT JOIN `atable` AS a ON `t`.`aid`=`a`.`tid` LEFT JOIN `btable` AS b ON `t`.`bid`=`b`.`tid` LEFT JOIN `ctable` AS c ON `t`.`cid`=`c`.`tid` LEFT JOIN `dtable` AS d ON `a`.`did`=`d`.`aid` LEFT JOIN `etable` AS e ON `b`.`eid`=`e`.`bid` LEFT JOIN `ftable` AS f ON `b`.`fid`=`f`.`bid` LEFT JOIN `gtable` AS g ON `d`.`gid`=`g`.`did` LEFT JOIN `htable` AS h ON `e`.`hid`=`h`.`eid` LEFT JOIN `itable` AS i ON `g`.`iid`=`i`.`gid` LEFT JOIN `jtable` AS j ON `g`.`jid`=`j`.`gid`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectInnerJoinWithAlias(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select(
                'cust.firstname',
                'cust.lastname',
                'cust.residence_city_id',
                'cust.notice_city_id',
                'residence_city.name as residence_city_name',
                'notice_city.name as notice_city_name'
            )
            ->from('customer cust')
            ->innerJoin('city residence_city','cust.residence_city_id=residence_city.city_id')
            ->innerJoin('city notice_city', 'cust.notice_city_id=notice_city.city_id');
        self::assertEquals(
            'SELECT `cust`.`firstname`, `cust`.`lastname`, `cust`.`residence_city_id`, `cust`.`notice_city_id`, `residence_city`.`name` as residence_city_name, `notice_city`.`name` as notice_city_name FROM `customer` AS cust INNER JOIN `city` AS residence_city ON `cust`.`residence_city_id`=`residence_city`.`city_id` INNER JOIN `city` AS notice_city ON `cust`.`notice_city_id`=`notice_city`.`city_id`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectCrossJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->crossJoin('btable');
        self::assertEquals(
            'SELECT `username` AS name, `age` as user_age FROM `atable` AS table CROSS JOIN `btable`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectRightJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->rightJoin('btable', 'table.id_btable = btable.id');
        self::assertEquals(
            'SELECT `username` AS name, `age` as user_age FROM `atable` AS table RIGHT JOIN `btable` ON `table`.`id_btable`=`btable`.`id`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectFullJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->fullJoin('btable', 'table.id_btable = btable.id');
        self::assertEquals(
            'SELECT `username` AS name, `age` as user_age FROM `atable` AS table FULL OUTER JOIN `btable` ON `table`.`id_btable`=`btable`.`id`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectNaturalJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->naturalJoin('btable');
        self::assertEquals(
            'SELECT `username` AS name, `age` as user_age FROM `atable` AS table NATURAL JOIN `btable`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectUnion(): void
    {
        $subQuery1 = new QueryBuilder()->select('username')->from('atable');
        $subQuery2 = new QueryBuilder()->select('username')->from('btable');
        $queryBuilder = new QueryBuilder();
        self::assertEquals(
            'SELECT `username` FROM `atable` UNION SELECT `username` FROM `btable`',
            $queryBuilder->union($subQuery1, $subQuery2)->getQuery()
        );
    }

    public function testQueryBuilderSelectUnions(): void
    {
        $subQuery1 = new QueryBuilder()->select('username')->from('atable');
        $subQuery2 = new QueryBuilder()->select('username')->from('btable');
        $subQuery3 = new QueryBuilder()->select('username')->from('ctable');
        $queryBuilder = new QueryBuilder();
        self::assertEquals(
            'SELECT `username` FROM `atable` UNION SELECT `username` FROM `btable` UNION SELECT `username` FROM `ctable`',
            $queryBuilder->union($subQuery1, $subQuery2, $subQuery3)
        );
    }

    public function testQueryBuilderSelectUnionAll(): void
    {
        $subQuery1 = new QueryBuilder()->select('username')->from('atable');
        $subQuery2 = new QueryBuilder()->select('username')->from('btable');
        $queryBuilder = new QueryBuilder();
        self::assertEquals(
            'SELECT `username` FROM `atable` UNION ALL SELECT `username` FROM `btable`',
            $queryBuilder->unionAll($subQuery1, $subQuery2)
        );
    }

    public function testQueryBuilderSelectUnionAlls(): void
    {
        $subQuery1 = new QueryBuilder()->select('username')->from('atable');
        $subQuery2 = new QueryBuilder()->select('username')->from('btable');
        $subQuery3 = new QueryBuilder()->select('username')->from('ctable');
        $queryBuilder = new QueryBuilder();
        self::assertEquals(
            'SELECT `username` FROM `atable` UNION ALL SELECT `username` FROM `btable` UNION ALL SELECT `username` FROM `ctable`',
            $queryBuilder->unionAll($subQuery1, $subQuery2, $subQuery3)
        );
    }

    public function testQueryBuilderSelectGroupBy(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('year', 'SUM(profit) AS profit')
            ->from('sales')
            ->groupBy('year');
        self::assertEquals(
            'SELECT `year`, SUM(profit) AS profit FROM `sales` GROUP BY `year`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectGroupByWithRollUp(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('year', 'SUM(profit) AS profit')
            ->from('sales')
            ->groupBy('year')
            ->withRollup();
        self::assertEquals(
            'SELECT `year`, SUM(profit) AS profit FROM `sales` GROUP BY `year` WITH ROLLUP',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where(SqlOperations::equal('pr.numprod', 'p.numprod'))
            ->andWhere(SqlOperations::equal('pr.catprod', 'category'));
        self::assertEquals(
            'SELECT COUNT(*) FROM `products` AS pr WHERE pr.numprod=p.numprod AND pr.catprod=category',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectNotWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where(SqlOperations::equal('pr.numprod', 'p.numprod'))
            ->andNotWhere(SqlOperations::equal('pr.catprod', 'category'));
        self::assertEquals(
            'SELECT COUNT(*) FROM `products` AS pr WHERE pr.numprod=p.numprod AND NOT pr.catprod=category',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectOrWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where(SqlOperations::equal('pr.numprod', 'p.numprod'))
            ->orWhere(SqlOperations::equal('pr.catprod', 'category'));
        self::assertEquals(
            'SELECT COUNT(*) FROM `products` AS pr WHERE pr.numprod=p.numprod OR pr.catprod=category',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectOrNotWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where(SqlOperations::equal('pr.numprod', 'p.numprod'))
            ->orNotWhere(SqlOperations::equal('pr.catprod', 'category'));
        self::assertEquals(
            'SELECT COUNT(*) FROM `products` AS pr WHERE pr.numprod=p.numprod OR NOT pr.catprod=category',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectManualWhere(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->manualWhere('pr.numprod=p.numprod');
        self::assertEquals(
            'SELECT COUNT(*) FROM `products` AS pr WHERE pr.numprod=p.numprod',
            $queryBuilder->getQuery()
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
            ->having(SqlOperations::greater('total', 1000))
            ->andHaving(SqlOperations::greater('itemsCount', 600))
            ->andNotHaving(SqlOperations::less('total', 10000));
        self::assertEquals(
            'SELECT `ordernumber`, SUM(quantityOrdered) AS itemsCount, SUM(priceeach*quantityOrdered) AS total FROM `orderdetails` GROUP BY `ordernumber` HAVING total>1000 AND itemsCount>600 AND NOT total<10000',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectHavingOr(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select(
                'ordernumber',
                'SUM(quantityOrdered) AS itemsCount',
                'SUM(priceeach*quantityOrdered) AS total'
            )
            ->from('orderdetails')
            ->groupBy('ordernumber')
            ->having(SqlOperations::notGreater('total', 1000))
            ->orHaving(SqlOperations::notGreater('itemsCount', 600))
            ->orNotHaving(SqlOperations::notGreater('total', 10000));
        self::assertEquals(
            'SELECT `ordernumber`, SUM(quantityOrdered) AS itemsCount, SUM(priceeach*quantityOrdered) AS total FROM `orderdetails` GROUP BY `ordernumber` HAVING total!>1000 OR itemsCount!>600 OR NOT total!>10000',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectManualHaving(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select(
                'ordernumber',
                'SUM(quantityOrdered) AS itemsCount',
                'SUM(priceeach*quantityOrdered) AS total'
            )
            ->from('orderdetails')
            ->groupBy('ordernumber')
            ->manualHaving('total>1000 OR itemsCount>600 OR NOT total>10000');
        self::assertEquals(
            'SELECT `ordernumber`, SUM(quantityOrdered) AS itemsCount, SUM(priceeach*quantityOrdered) AS total FROM `orderdetails` GROUP BY `ordernumber` HAVING total>1000 OR itemsCount>600 OR NOT total>10000',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectSubQueryIntoSelect(): void
    {
        $subQuery = new QueryBuilder();
        $subQuery
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where(SqlOperations::equal('pr.numprod', 'p.numprod'));
        $queryBuilder = new QueryBuilder()
            ->select('product_name', $subQuery->getSubQuery() . ' as nb_supplier')
            ->from('produit', 'p');
        self::assertEquals(
            'SELECT `product_name`, (SELECT COUNT(*) FROM `products` AS pr WHERE pr.numprod=p.numprod) as nb_supplier FROM `produit` AS p',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectSubQueryIntoFrom(): void
    {
        $subQuery = new QueryBuilder();
        $subQuery
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where(SqlOperations::equal('pr.numprod', 'p.numprod'));
        $queryBuilder = new QueryBuilder()
            ->select('product_name', $subQuery->getSubQuery() . ' as nb_supplier')
            ->from('produit', 'p');
        self::assertEquals(
            'SELECT `product_name`, (SELECT COUNT(*) FROM `products` AS pr WHERE pr.numprod=p.numprod) as nb_supplier FROM `produit` AS p',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderUpdate(): void
    {
        $queryBuilder = new QueryBuilder()
            ->update('employees')
            ->set('lastname', 'Hill')
            ->set('firstname', 'John');
        self::assertEquals(
            'UPDATE `employees` SET `lastname` = :namedParam1, `firstname` = :namedParam2',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderUpdateAlias(): void
    {
        $queryBuilder = new QueryBuilder()->update('employees', 'emp')->set('lastname', 'Hill');
        self::assertEquals(
            'UPDATE `employees` AS emp SET `lastname` = :namedParam1',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderUpdateWithWhereAndNamedParameters(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->update('employees')
            ->setValue('lastname', 'Hill')
            ->where(SqlOperations::equal('id', $queryBuilder->addNamedParameter(33806, PDO::PARAM_INT)));
        self::assertEquals(
            'UPDATE `employees` SET `lastname` = :namedParam1 WHERE id=:namedParam2',
            $queryBuilder->getQuery()
        );
        self::assertEquals(
            [':namedParam1' => ['Hill', 2], ':namedParam2' => [33806, 1]],
            $queryBuilder->getNamedParameters()
        );
    }

    public function testQueryBuilderDelete(): void
    {
        $queryBuilder = new QueryBuilder()
            ->delete('employees')
            ->where(SqlOperations::equal('employees.id', 1));
        self::assertEquals(
            'DELETE FROM `employees` WHERE employees.id=1',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectSubQueryIntoFromWithUnion(): void
    {
        $kitrepairs = new QueryBuilder()
            ->select(
                'aux_repairs.id AS id',
                'aux_repairs.equipment_table AS equipment_table',
                'aux_repairs.idequipment AS idequipment',
                'failures.description AS failuresdescription',
                'aux_repairs.id_failures AS id_failures',
                'aux_repairs.comment AS comment',
                'aux_repairs.date_failure AS date_failure',
                'aux_kits.name AS itemname',
                'units.name AS unitsname'
            )
            ->from('aux_repairs')
            ->leftJoin('failures', 'aux_repairs.id_failures = failures.id')
            ->leftJoin('aux_kits', 'aux_repairs.idequipment = aux_kits.id')
            ->leftJoin('units', 'aux_kits.id_units = units.id')
            ->where(SqlOperations::equal(AbstractQueryBuilder::escapeIdentifier('aux_repairs.equipment_table'), "'aux_kits'"));
        $phonerepairs = new QueryBuilder()
            ->select(
                'aux_repairs.id AS id',
                'aux_repairs.equipment_table AS equipment_table',
                'aux_repairs.idequipment AS idequipment',
                'failures.description AS failuresdescription',
                'aux_repairs.id_failures AS id_failures',
                'aux_repairs.comment AS comment',
                'aux_repairs.date_failure AS date_failure',
                'aux_phones.name AS itemname',
                'units.name AS unitsname'
            )
            ->from('aux_repairs')
            ->leftJoin('failures', 'aux_repairs.id_failures = failures.id')
            ->leftJoin('aux_phones', 'aux_repairs.idequipment = aux_phones.id')
            ->leftJoin('units', 'aux_phones.id_units = units.id')
            ->where(SqlOperations::equal(AbstractQueryBuilder::escapeIdentifier('aux_repairs.equipment_table'), "'aux_phones'"));
        $subQuery = new QueryBuilder()->union($kitrepairs, $phonerepairs);
        $queryBuilder = new QueryBuilder()->select('*')->from($subQuery, 'aux_repairs');
        self::assertEquals(
            "SELECT * FROM (SELECT `aux_repairs`.`id` AS id, `aux_repairs`.`equipment_table` AS equipment_table, `aux_repairs`.`idequipment` AS idequipment, `failures`.`description` AS failuresdescription, `aux_repairs`.`id_failures` AS id_failures, `aux_repairs`.`comment` AS comment, `aux_repairs`.`date_failure` AS date_failure, `aux_kits`.`name` AS itemname, `units`.`name` AS unitsname FROM `aux_repairs` LEFT JOIN `failures` ON `aux_repairs`.`id_failures`=`failures`.`id` LEFT JOIN `aux_kits` ON `aux_repairs`.`idequipment`=`aux_kits`.`id` LEFT JOIN `units` ON `aux_kits`.`id_units`=`units`.`id` WHERE `aux_repairs`.`equipment_table`='aux_kits' UNION SELECT `aux_repairs`.`id` AS id, `aux_repairs`.`equipment_table` AS equipment_table, `aux_repairs`.`idequipment` AS idequipment, `failures`.`description` AS failuresdescription, `aux_repairs`.`id_failures` AS id_failures, `aux_repairs`.`comment` AS comment, `aux_repairs`.`date_failure` AS date_failure, `aux_phones`.`name` AS itemname, `units`.`name` AS unitsname FROM `aux_repairs` LEFT JOIN `failures` ON `aux_repairs`.`id_failures`=`failures`.`id` LEFT JOIN `aux_phones` ON `aux_repairs`.`idequipment`=`aux_phones`.`id` LEFT JOIN `units` ON `aux_phones`.`id_units`=`units`.`id` WHERE `aux_repairs`.`equipment_table`='aux_phones') AS aux_repairs",
            $queryBuilder->getQuery()
        );
    }
}