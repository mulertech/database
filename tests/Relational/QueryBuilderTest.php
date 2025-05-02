<?php

namespace MulerTech\Database\Tests\Relational;

use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\Database\Relational\Sql\SqlQuery;
use PDO;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{

    public function testQueryBuilderInsertOneDynamicParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->insert('atable')
            ->setValue('column1', $queryBuilder->addDynamicParameter('data column 1'));
        self::assertEquals('INSERT INTO `atable` (`column1`) VALUES (?)', $queryBuilder->getQuery());
    }

    public function testQueryBuilderInsertOneNameParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->insert('atable')
            ->setValue('column1', ':namedParam1')->addNamedParameter('data column 1');
        self::assertEquals('INSERT INTO `atable` (`column1`) VALUES (:namedParam1)', $queryBuilder->getQuery());
    }

    public function testQueryBuilderInsertDynamicParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->insert('atable as table')
            ->setValue('column1', $queryBuilder->addDynamicParameter('data column 1'))
            ->setValue('column2', '?')->addDynamicParameter('data column 2');
        self::assertEquals(
            'INSERT INTO `atable` `table` (`column1`, `column2`) VALUES (?, ?)',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderInsertDynamicParameterWithNumber(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->insert('atable AS table')
            ->setValue('column1', '?')->setParameter(1, 'data column 1');
        $queryBuilder->setValue('column2', '?')->setParameter(2, 'data column 2');
        self::assertEquals(
            'INSERT INTO `atable` `table` (`column1`, `column2`) VALUES (?, ?)',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderInsertAllDynamicParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValues(
            [
                ['column1', $queryBuilder->addDynamicParameter('data column 1')],
                ['column2', $queryBuilder->addDynamicParameter('data column 2')]
            ]
        );
        self::assertEquals('INSERT INTO `atable` (`column1`, `column2`) VALUES (?, ?)', $queryBuilder->getQuery());
    }

    public function testQueryBuilderInsertNamedAndDynamicParameter(): void
    {
        $this->expectExceptionMessage(
            'Class QueryBuilder, function addDynamicParameter. A dynamic parameter can\'t be define because one or more named parameter is already defined.'
        );
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', $queryBuilder->addNamedParameter('data column 1'));
        $queryBuilder->setValue('column2', $queryBuilder->addDynamicParameter('data column 2'));
    }

    public function testQueryBuilderGetExecuteNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', $queryBuilder->addNamedParameter('data column 1'));
        $queryBuilder->setValue('column2', $queryBuilder->addNamedParameter('data column 2'));
        self::assertEquals(
            [':namedParam1' => 'data column 1', ':namedParam2' => 'data column 2'],
            $queryBuilder->getExecuteParameters()
        );
    }

    public function testQueryBuilderGetExecuteDynamicParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', $queryBuilder->addDynamicParameter('data column 1'));
        $queryBuilder->setValue('column2', $queryBuilder->addDynamicParameter('data column 2'));
        self::assertEquals([1 => 'data column 1', 2 => 'data column 2'], $queryBuilder->getExecuteParameters());
    }

    public function testQueryBuilderGetBindNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', $queryBuilder->addNamedParameter('data column 1'));
        $queryBuilder->setValue('column2', $queryBuilder->addNamedParameter('data column 2', PDO::PARAM_INT));
        self::assertEquals(
            [[':namedParam1', 'data column 1', 2], [':namedParam2', 'data column 2', 1]],
            $queryBuilder->getBindParameters()
        );
    }

    public function testQueryBuilderGetBindDynamicParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', $queryBuilder->addDynamicParameter('data column 1'));
        $queryBuilder->setValue(
            'column2',
            $queryBuilder->addDynamicParameter('data column 2', PDO::PARAM_INT)
        );
        self::assertEquals([[1, 'data column 1', 2], [2, 'data column 2', 1]], $queryBuilder->getBindParameters());
    }

    public function testQueryBuilderNullGetBindParameters(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        self::assertEquals(null, $queryBuilder->getBindParameters());
    }

    public function testQueryBuilderInsertDynamicAndNamedParameter(): void
    {
        $this->expectExceptionMessage(
            'Class QueryBuilder, function addNamedParameter. A named parameter can\'t be define because one or more dynamic parameter is already defined.'
        );
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', $queryBuilder->addDynamicParameter('data column 1'));
        $queryBuilder->setValue('column2', $queryBuilder->addNamedParameter('data column 2'));
    }

    public function testQueryBuilderInsertNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValue('column1', $queryBuilder->addNamedParameter('data column 1'));
        $queryBuilder
            ->setValue('column2', 'column2value')
            ->setParameter('column2value', 'data column 2');
        self::assertEquals(
            'INSERT INTO `atable` (`column1`, `column2`) VALUES (:namedParam1, :namedParam2)',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderInsertAllNamedParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->insert('atable');
        $queryBuilder->setValues(
            [
                ['column1', $queryBuilder->addNamedParameter('data column 1')],
                ['column2', $queryBuilder->addNamedParameter('data column 2')]
            ]
        );
        self::assertEquals(
            'INSERT INTO `atable` (`column1`, `column2`) VALUES (:namedParam1, :namedParam2)',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderWithNoType(): void
    {
        $this->expectExceptionMessage(
            'Class SqlQuery, function generateFrom. The type (not define) of the query (for the "atable" table) into the QueryBuilder was not define or incorrect.'
        );
        new QueryBuilder()->from('atable')->getQuery();
    }

    public function testQueryBuilderWithoutFrom(): void
    {
        $this->expectExceptionMessage(
            'Class SqlQuery, function generateFrom. The from variable was not found, for the "SELECT" request.'
        );
        new QueryBuilder()->select('*')->getQuery();
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
            ->from('food')->addFrom('food_menu')
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
            ->from('food', 'foodas')->addFrom('food_menu', 'menuas')
            ->where(SqlOperations::equal('food_id', 1));
        self::assertEquals(
            'SELECT `name`, `price`, `options`, `photo` FROM `food` `foodas`, `food_menu` `menuas` WHERE food_id=1',
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
            'SELECT `sc1`, `sc2`, `sc3` FROM (SELECT `c1` `sc1`, `c2` `sc2`, `c3` `sc3` FROM `tb1`) `sb` WHERE sc1>1',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectWithFromSubQuerySelectAlias(): void
    {
        $from = new QueryBuilder();
        $from->select('c1 sc1', 'c2 sc2', 'c3 sc3')->from('tb1')->selectAlias('sb');
        $queryBuilder = new QueryBuilder()
            ->select('sc1', 'sc2', 'sc3')
            ->from($from)
            ->where(SqlOperations::greater('sc1', 1));
        self::assertEquals(
            'SELECT `sc1`, `sc2`, `sc3` FROM (SELECT `c1` `sc1`, `c2` `sc2`, `c3` `sc3` FROM `tb1`) AS sb WHERE sc1>1',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectLimit(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('*')
            ->from('atable')
            ->limit(10)
            ->offset(5, 10);
        self::assertEquals('SELECT * FROM `atable` LIMIT 10 OFFSET 40', $queryBuilder->getQuery());
    }

    public function testQueryBuilderSelectWithoutLimit(): void
    {
        $this->expectExceptionMessage(
            'Class QueryBuilder, function : offset. The limit must be define before the offset.'
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
            'SELECT `username` `name`, `age` `user_age` FROM `atable` ORDER BY `age` DESC, `username` ASC',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectInnerJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('customername', 'customercity', 'customermail', 'ordertotal', 'salestotal')
            ->from('customers c')
            ->innerJoin('customers c', 'orders o', 'c.customerid=o.customerid')
            ->leftJoin('orders o', 'sales s', 'o.orderid=s.orderid');
        self::assertEquals(
            'SELECT `customername`, `customercity`, `customermail`, `ordertotal`, `salestotal` FROM `customers` `c` INNER JOIN `orders` `o` ON `c`.`customerid`=`o`.`customerid` LEFT JOIN `sales` `s` ON `o`.`orderid`=`s`.`orderid`',
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
                'INFORMATION_SCHEMA.KEY_COLUMN_USAGE k',
                'INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r',
                'k.CONSTRAINT_NAME=r.CONSTRAINT_NAME'
            )
            ->where(SqlOperations::equal('k.CONSTRAINT_SCHEMA', "'db'"))
            ->andWhere('k.REFERENCED_TABLE_SCHEMA IS NOT NULL')
            ->andWhere('k.REFERENCED_TABLE_NAME IS NOT NULL')
            ->andWhere('k.REFERENCED_COLUMN_NAME IS NOT NULL');
        self::assertEquals(
            'SELECT `k`.`TABLE_NAME`, `k`.`CONSTRAINT_NAME`, `k`.`COLUMN_NAME`, `k`.`REFERENCED_TABLE_SCHEMA`, `k`.`REFERENCED_TABLE_NAME`, `k`.`REFERENCED_COLUMN_NAME`, `r`.`DELETE_RULE`, `r`.`UPDATE_RULE` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` `k` LEFT JOIN `INFORMATION_SCHEMA`.`REFERENTIAL_CONSTRAINTS` `r` ON `k`.`CONSTRAINT_NAME`=`r`.`CONSTRAINT_NAME` WHERE k.CONSTRAINT_SCHEMA=\'db\' AND k.REFERENCED_TABLE_SCHEMA IS NOT NULL AND k.REFERENCED_TABLE_NAME IS NOT NULL AND k.REFERENCED_COLUMN_NAME IS NOT NULL',
            $queryBuilder->getQuery()
        );
    }
    public function testQueryBuilderSelectInnerJoinAndLeftJoinTableNotFound(): void
    {
        $this->expectExceptionMessage(
            'Class : QueryBuilder, Function : addJoin. Unable to find the from table "unknown u" given for add join of type : LEFT JOIN'
        );
        new QueryBuilder()
            ->select('customername', 'customercity', 'customermail', 'ordertotal', 'salestotal')
            ->from('customers c')
            ->innerJoin('customers c', 'orders o', 'c.customerid=o.customerid')
            ->leftJoin('unknown u', 'sales s', 'o.orderid=s.orderid');
    }

    public function testQueryBuilderSelectInnerJoinAndLeftJoinAliasUsed(): void
    {
        $this->expectExceptionMessage(
            'Class : QueryBuilder, Function : addJoin. The alias "o" for join of type "LEFT JOIN" is used.'
        );
        new QueryBuilder()
            ->select('customername', 'customercity', 'customermail', 'ordertotal', 'salestotal')
            ->from('customers c')
            ->innerJoin('customers c', 'orders o', 'c.customerid=o.customerid')
            ->leftJoin('customers c', 'otherorders o', 'o.orderid=s.orderid');
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
            ->leftJoin('basetable', 'atable a', 'basetable.aid=a.tid')
            ->leftJoin('atable a', 'btable b', 'a.bid=b.aid')
            ->leftJoin('btable b', 'ctable c', 'b.cid=c.bid')
            ->leftJoin('ctable c', 'dtable d', 'c.did=d.cid')
            ->leftJoin('dtable d', 'etable e', 'd.eid=e.did')
            ->leftJoin('etable e', 'ftable f', 'e.fid=f.eid')
            ->leftJoin('ftable f', 'gtable', 'f.gid=gtable.fid')
            ->leftJoin('gtable', 'htable', 'gtable.hid=htable.gid')
            ->leftJoin('htable', 'itable', 'htable.iid=itable.hid')
            ->leftJoin('itable', 'jtable', 'itable.jid=jtable.iid');
        self::assertEquals(
            'SELECT `basecolumn`, `acolumn`, `bcolumn`, `ccolumn`, `dcolumn`, `ecolumn`, `fcolumn`, `gcolumn`, `hcolumn`, `icolumn`, `jcolumn` FROM `basetable` LEFT JOIN `atable` `a` ON `basetable`.`aid`=`a`.`tid` LEFT JOIN `btable` `b` ON `a`.`bid`=`b`.`aid` LEFT JOIN `ctable` `c` ON `b`.`cid`=`c`.`bid` LEFT JOIN `dtable` `d` ON `c`.`did`=`d`.`cid` LEFT JOIN `etable` `e` ON `d`.`eid`=`e`.`did` LEFT JOIN `ftable` `f` ON `e`.`fid`=`f`.`eid` LEFT JOIN `gtable` ON `f`.`gid`=`gtable`.`fid` LEFT JOIN `htable` ON `gtable`.`hid`=`htable`.`gid` LEFT JOIN `itable` ON `htable`.`iid`=`itable`.`hid` LEFT JOIN `jtable` ON `itable`.`jid`=`jtable`.`iid`',
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
            ->leftJoin('basetable t', 'atable alpha', 't.aid=alpha.tid')
            ->leftJoin('basetable t', 'atable bravo', 't.bid=bravo.tid')
            ->leftJoin('basetable t', 'atable charlie', 't.cid=charlie.tid')
            ->leftJoin('atable alpha', 'btable', 'alpha.btableid=btable.alphaid');
        self::assertEquals(
            'SELECT `basecolumn`, `alphacolumn`, `bravocolumn`, `charliecolumn`, `bcolumn` FROM `basetable` `t` LEFT JOIN `atable` `alpha` ON `t`.`aid`=`alpha`.`tid` LEFT JOIN `atable` `bravo` ON `t`.`bid`=`bravo`.`tid` LEFT JOIN `atable` `charlie` ON `t`.`cid`=`charlie`.`tid` LEFT JOIN `btable` ON `alpha`.`btableid`=`btable`.`alphaid`',
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
            ->leftJoin('basetable t', 'atable a', 't.aid=a.tid')
            ->leftJoin('basetable t', 'btable b', 't.bid=b.tid')
            ->leftJoin('basetable t', 'ctable c', 't.cid=c.tid')
            ->leftJoin('atable a', 'dtable d', 'a.did=d.aid')
            ->leftJoin('btable b', 'etable e', 'b.eid=e.bid')
            ->leftJoin('btable b', 'ftable f', 'b.fid=f.bid')
            ->leftJoin('dtable d', 'gtable g', 'd.gid=g.did')
            ->leftJoin('etable e', 'htable h', 'e.hid=h.eid')
            ->leftJoin('gtable g', 'itable i', 'g.iid=i.gid')
            ->leftJoin('gtable g', 'jtable j', 'g.jid=j.gid');
        self::assertEquals(
            'SELECT `basecolumn`, `acolumn`, `bcolumn`, `ccolumn`, `dcolumn`, `ecolumn`, `fcolumn`, `gcolumn`, `hcolumn`, `icolumn`, `jcolumn` FROM `basetable` `t` LEFT JOIN `atable` `a` ON `t`.`aid`=`a`.`tid` LEFT JOIN `btable` `b` ON `t`.`bid`=`b`.`tid` LEFT JOIN `ctable` `c` ON `t`.`cid`=`c`.`tid` LEFT JOIN `dtable` `d` ON `a`.`did`=`d`.`aid` LEFT JOIN `etable` `e` ON `b`.`eid`=`e`.`bid` LEFT JOIN `ftable` `f` ON `b`.`fid`=`f`.`bid` LEFT JOIN `gtable` `g` ON `d`.`gid`=`g`.`did` LEFT JOIN `htable` `h` ON `e`.`hid`=`h`.`eid` LEFT JOIN `itable` `i` ON `g`.`iid`=`i`.`gid` LEFT JOIN `jtable` `j` ON `g`.`jid`=`j`.`gid`',
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
            ->innerJoin(
                'customer cust',
                'city residence_city',
                'cust.residence_city_id=residence_city.city_id'
            )
            ->innerJoin('customer cust', 'city notice_city', 'cust.notice_city_id=notice_city.city_id');
        self::assertEquals(
            'SELECT `cust`.`firstname`, `cust`.`lastname`, `cust`.`residence_city_id`, `cust`.`notice_city_id`, `residence_city`.`name` `residence_city_name`, `notice_city`.`name` `notice_city_name` FROM `customer` `cust` INNER JOIN `city` `residence_city` ON `cust`.`residence_city_id`=`residence_city`.`city_id` INNER JOIN `city` `notice_city` ON `cust`.`notice_city_id`=`notice_city`.`city_id`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectCrossJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->crossJoin('atable table', 'btable');
        self::assertEquals(
            'SELECT `username` `name`, `age` `user_age` FROM `atable` `table` CROSS JOIN `btable`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectRightJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->rightJoin('atable table', 'btable', 'table.id_btable = btable.id');
        self::assertEquals(
            'SELECT `username` `name`, `age` `user_age` FROM `atable` `table` RIGHT JOIN `btable` ON `table`.`id_btable`=`btable`.`id`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectFullJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->fullJoin('atable table', 'btable', 'table.id_btable = btable.id');
        self::assertEquals(
            'SELECT `username` `name`, `age` `user_age` FROM `atable` `table` FULL JOIN `btable` ON `table`.`id_btable`=`btable`.`id`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectNaturalJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->naturalJoin('atable table', 'btable', 'table.id_btable = btable.id');
        self::assertEquals(
            'SELECT `username` `name`, `age` `user_age` FROM `atable` `table` NATURAL JOIN `btable` ON `table`.`id_btable`=`btable`.`id`',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectUnionJoin(): void
    {
        $queryBuilder = new QueryBuilder()
            ->select('username AS name', 'age as user_age')
            ->from('atable table')
            ->unionJoin('atable table', 'btable', 'table.id_btable = btable.id');
        self::assertEquals(
            'SELECT `username` `name`, `age` `user_age` FROM `atable` `table` UNION JOIN `btable` ON `table`.`id_btable`=`btable`.`id`',
            $queryBuilder->getQuery()
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
            'SELECT COUNT(*) FROM `products` `pr` WHERE pr.numprod=p.numprod AND pr.catprod=category',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderSelectWhereDynamicParameter(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->select('COUNT(*)')
            ->from('products', 'pr')
            ->where(SqlOperations::equal('pr.numprod', 'p.numprod'))
            ->andWhere(SqlOperations::equal('pr.catprod', $queryBuilder->addDynamicParameter('category')));
        self::assertEquals(
            'SELECT COUNT(*) FROM `products` `pr` WHERE pr.numprod=p.numprod AND pr.catprod=?',
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
            'SELECT COUNT(*) FROM `products` `pr` WHERE pr.numprod=p.numprod AND NOT pr.catprod=category',
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
            'SELECT COUNT(*) FROM `products` `pr` WHERE pr.numprod=p.numprod OR pr.catprod=category',
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
            'SELECT COUNT(*) FROM `products` `pr` WHERE pr.numprod=p.numprod OR NOT pr.catprod=category',
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
            'SELECT COUNT(*) FROM `products` `pr` WHERE pr.numprod=p.numprod',
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
            'SELECT `product_name`, (SELECT COUNT(*) FROM `products` `pr` WHERE pr.numprod=p.numprod) as nb_supplier FROM `produit` `p`',
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
            'SELECT `product_name`, (SELECT COUNT(*) FROM `products` `pr` WHERE pr.numprod=p.numprod) as nb_supplier FROM `produit` `p`',
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
            'UPDATE `employees` SET `lastname` = ?, `firstname` = ?',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderUpdateAlias(): void
    {
        $queryBuilder = new QueryBuilder()->update('employees', 'emp')->set('lastname', 'Hill');
        self::assertEquals(
            'UPDATE `employees` `emp` SET `lastname` = ?',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderUpdateWithWhereAndDynamicParameters(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->update('employees')->set('lastname', 'Hill')->where(SqlOperations::equal('id', $queryBuilder->addDynamicParameter(1)));
        self::assertEquals(
            'UPDATE `employees` SET `lastname` = ? WHERE id=?',
            $queryBuilder->getQuery()
        );
    }

    public function testQueryBuilderUpdateWithWhereAndNamedParameters(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->update('employees')
            ->setValue('lastname', $queryBuilder->addNamedParameter('Hill'))
            ->where(SqlOperations::equal('id', $queryBuilder->addNamedParameter(1)));
        self::assertEquals(
            'UPDATE `employees` SET `lastname` = :namedParam1 WHERE id=:namedParam2',
            $queryBuilder->getQuery()
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
            ->leftJoin('aux_repairs', 'failures', 'aux_repairs.id_failures = failures.id')
            ->leftJoin('aux_repairs', 'aux_kits', 'aux_repairs.idequipment = aux_kits.id')
            ->leftJoin('aux_kits', 'units', 'aux_kits.id_units = units.id')
            ->where(SqlOperations::equal(SqlQuery::escape('aux_repairs.equipment_table'), "'aux_kits'"));
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
            ->leftJoin('aux_repairs', 'failures', 'aux_repairs.id_failures = failures.id')
            ->leftJoin('aux_repairs', 'aux_phones', 'aux_repairs.idequipment = aux_phones.id')
            ->leftJoin('aux_phones', 'units', 'aux_phones.id_units = units.id')
            ->where(SqlOperations::equal(SqlQuery::escape('aux_repairs.equipment_table'), "'aux_phones'"));
        $subQuery = new QueryBuilder()->union($kitrepairs, $phonerepairs);
        $queryBuilder = new QueryBuilder()->select('*')->from($subQuery, 'aux_repairs');
        self::assertEquals(
            "SELECT * FROM (SELECT `aux_repairs`.`id` `id`, `aux_repairs`.`equipment_table` `equipment_table`, `aux_repairs`.`idequipment` `idequipment`, `failures`.`description` `failuresdescription`, `aux_repairs`.`id_failures` `id_failures`, `aux_repairs`.`comment` `comment`, `aux_repairs`.`date_failure` `date_failure`, `aux_kits`.`name` `itemname`, `units`.`name` `unitsname` FROM `aux_repairs` LEFT JOIN `failures` ON `aux_repairs`.`id_failures`=`failures`.`id` LEFT JOIN `aux_kits` ON `aux_repairs`.`idequipment`=`aux_kits`.`id` LEFT JOIN `units` ON `aux_kits`.`id_units`=`units`.`id` WHERE `aux_repairs`.`equipment_table`='aux_kits' UNION SELECT `aux_repairs`.`id` `id`, `aux_repairs`.`equipment_table` `equipment_table`, `aux_repairs`.`idequipment` `idequipment`, `failures`.`description` `failuresdescription`, `aux_repairs`.`id_failures` `id_failures`, `aux_repairs`.`comment` `comment`, `aux_repairs`.`date_failure` `date_failure`, `aux_phones`.`name` `itemname`, `units`.`name` `unitsname` FROM `aux_repairs` LEFT JOIN `failures` ON `aux_repairs`.`id_failures`=`failures`.`id` LEFT JOIN `aux_phones` ON `aux_repairs`.`idequipment`=`aux_phones`.`id` LEFT JOIN `units` ON `aux_phones`.`id_units`=`units`.`id` WHERE `aux_repairs`.`equipment_table`='aux_phones') `aux_repairs`",
            $queryBuilder->getQuery()
        );
    }
}