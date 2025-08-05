<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Information;

use MulerTech\Database\Schema\Information\InformationSchemaTables;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for InformationSchemaTables enum
 */
class InformationSchemaTablesTest extends TestCase
{
    public function testEnumValues(): void
    {
        // Test core information schema tables
        $this->assertEquals('TABLES', InformationSchemaTables::TABLES->value);
        $this->assertEquals('COLUMNS', InformationSchemaTables::COLUMNS->value);
        $this->assertEquals('KEY_COLUMN_USAGE', InformationSchemaTables::KEY_COLUMN_USAGE->value);
        $this->assertEquals('REFERENTIAL_CONSTRAINTS', InformationSchemaTables::REFERENTIAL_CONSTRAINTS->value);
    }

    public function testSchemaInformationTables(): void
    {
        $this->assertEquals('SCHEMATA', InformationSchemaTables::SCHEMATA->value);
        $this->assertEquals('TABLE_CONSTRAINTS', InformationSchemaTables::TABLE_CONSTRAINTS->value);
        $this->assertEquals('TABLE_PRIVILEGES', InformationSchemaTables::TABLE_PRIVILEGES->value);
        $this->assertEquals('COLUMN_PRIVILEGES', InformationSchemaTables::COLUMN_PRIVILEGES->value);
    }

    public function testCharacterAndCollationTables(): void
    {
        $this->assertEquals('CHARACTER_SETS', InformationSchemaTables::CHARACTER_SETS->value);
        $this->assertEquals('COLLATIONS', InformationSchemaTables::COLLATIONS->value);
        $this->assertEquals('COLLATION_CHARACTER_SET_APPLICABILITY', InformationSchemaTables::COLLATION_CHARACTER_SET_APPLICABILITY->value);
    }

    public function testRoutinesAndTriggers(): void
    {
        $this->assertEquals('ROUTINES', InformationSchemaTables::ROUTINES->value);
        $this->assertEquals('TRIGGERS', InformationSchemaTables::TRIGGERS->value);
        $this->assertEquals('EVENTS', InformationSchemaTables::EVENTS->value);
        $this->assertEquals('PARAMETERS', InformationSchemaTables::PARAMETERS->value);
    }

    public function testViewTables(): void
    {
        $this->assertEquals('VIEWS', InformationSchemaTables::VIEWS->value);
        $this->assertEquals('VIEW_ROUTINE_USAGE', InformationSchemaTables::VIEW_ROUTINE_USAGE->value);
        $this->assertEquals('VIEW_TABLE_USAGE', InformationSchemaTables::VIEW_TABLE_USAGE->value);
    }

    public function testInnoDBTables(): void
    {
        $this->assertEquals('INNODB_TABLES', InformationSchemaTables::INNODB_TABLES->value);
        $this->assertEquals('INNODB_COLUMNS', InformationSchemaTables::INNODB_COLUMNS->value);
        $this->assertEquals('INNODB_INDEXES', InformationSchemaTables::INNODB_INDEXES->value);
        $this->assertEquals('INNODB_FOREIGN', InformationSchemaTables::INNODB_FOREIGN->value);
        $this->assertEquals('INNODB_FOREIGN_COLS', InformationSchemaTables::INNODB_FOREIGN_COLS->value);
    }

    public function testInnoDBBufferTables(): void
    {
        $this->assertEquals('INNODB_BUFFER_PAGE', InformationSchemaTables::INNODB_BUFFER_PAGE->value);
        $this->assertEquals('INNODB_BUFFER_PAGE_LRU', InformationSchemaTables::INNODB_BUFFER_PAGE_LRU->value);
        $this->assertEquals('INNODB_BUFFER_POOL_STATS', InformationSchemaTables::INNODB_BUFFER_POOL_STATS->value);
    }

    public function testInnoDBCompressionTables(): void
    {
        $this->assertEquals('INNODB_CMP', InformationSchemaTables::INNODB_CMP->value);
        $this->assertEquals('INNODB_CMP_RESET', InformationSchemaTables::INNODB_CMP_RESET->value);
        $this->assertEquals('INNODB_CMP_PER_INDEX', InformationSchemaTables::INNODB_CMP_PER_INDEX->value);
        $this->assertEquals('INNODB_CMP_PER_INDEX_RESET', InformationSchemaTables::INNODB_CMP_PER_INDEX_RESET->value);
        $this->assertEquals('INNODB_CMPMEM', InformationSchemaTables::INNODB_CMPMEM->value);
        $this->assertEquals('INNODB_CMPMEM_RESET', InformationSchemaTables::INNODB_CMPMEM_RESET->value);
    }

    public function testInnoDBFullTextTables(): void
    {
        $this->assertEquals('INNODB_FT_CONFIG', InformationSchemaTables::INNODB_FT_CONFIG->value);
        $this->assertEquals('INNODB_FT_BEING_DELETED', InformationSchemaTables::INNODB_FT_BEING_DELETED->value);
        $this->assertEquals('INNODB_FT_DELETED', InformationSchemaTables::INNODB_FT_DELETED->value);
        $this->assertEquals('INNODB_FT_DEFAULT_STOPWORD', InformationSchemaTables::INNODB_FT_DEFAULT_STOPWORD->value);
        $this->assertEquals('INNODB_FT_INDEX_CACHE', InformationSchemaTables::INNODB_FT_INDEX_CACHE->value);
        $this->assertEquals('INNODB_FT_INDEX_TABLE', InformationSchemaTables::INNODB_FT_INDEX_TABLE->value);
    }

    public function testInnoDBTablespacesTables(): void
    {
        $this->assertEquals('INNODB_TABLESPACES', InformationSchemaTables::INNODB_TABLESPACES->value);
        $this->assertEquals('INNODB_TABLESPACES_BRIEF', InformationSchemaTables::INNODB_TABLESPACES_BRIEF->value);
        $this->assertEquals('INNODB_DATAFILES', InformationSchemaTables::INNODB_DATAFILES->value);
        $this->assertEquals('INNODB_SESSION_TEMP_TABLESPACES', InformationSchemaTables::INNODB_SESSION_TEMP_TABLESPACES->value);
    }

    public function testInnoDBMiscTables(): void
    {
        $this->assertEquals('INNODB_METRICS', InformationSchemaTables::INNODB_METRICS->value);
        $this->assertEquals('INNODB_TABLESTATS', InformationSchemaTables::INNODB_TABLESTATS->value);
        $this->assertEquals('INNODB_TEMP_TABLE_INFO', InformationSchemaTables::INNODB_TEMP_TABLE_INFO->value);
        $this->assertEquals('INNODB_TRX', InformationSchemaTables::INNODB_TRX->value);
        $this->assertEquals('INNODB_VIRTUAL', InformationSchemaTables::INNODB_VIRTUAL->value);
        $this->assertEquals('INNODB_CACHED_INDEXES', InformationSchemaTables::INNODB_CACHED_INDEXES->value);
        $this->assertEquals('INNODB_FIELDS', InformationSchemaTables::INNODB_FIELDS->value);
    }

    public function testRolesTables(): void
    {
        $this->assertEquals('ADMINISTRABLE_ROLE_AUTHORIZATIONS', InformationSchemaTables::ADMINISTRABLE_ROLE_AUTHORIZATIONS->value);
        $this->assertEquals('APPLICABLE_ROLES', InformationSchemaTables::APPLICABLE_ROLES->value);
        $this->assertEquals('ENABLED_ROLES', InformationSchemaTables::ENABLED_ROLES->value);
        $this->assertEquals('ROLE_COLUMN_GRANTS', InformationSchemaTables::ROLE_COLUMN_GRANTS->value);
        $this->assertEquals('ROLE_ROUTINE_GRANTS', InformationSchemaTables::ROLE_ROUTINE_GRANTS->value);
        $this->assertEquals('ROLE_TABLE_GRANTS', InformationSchemaTables::ROLE_TABLE_GRANTS->value);
    }

    public function testSystemTables(): void
    {
        $this->assertEquals('ENGINES', InformationSchemaTables::ENGINES->value);
        $this->assertEquals('FILES', InformationSchemaTables::FILES->value);
        $this->assertEquals('KEYWORDS', InformationSchemaTables::KEYWORDS->value);
        $this->assertEquals('PLUGINS', InformationSchemaTables::PLUGINS->value);
        $this->assertEquals('PROCESSLIST', InformationSchemaTables::PROCESSLIST->value);
        $this->assertEquals('PROFILING', InformationSchemaTables::PROFILING->value);
    }

    public function testConstraintsTables(): void
    {
        $this->assertEquals('CHECK_CONSTRAINTS', InformationSchemaTables::CHECK_CONSTRAINTS->value);
        $this->assertEquals('TABLE_CONSTRAINTS_EXTENSIONS', InformationSchemaTables::TABLE_CONSTRAINTS_EXTENSIONS->value);
    }

    public function testStatisticsTables(): void
    {
        $this->assertEquals('STATISTICS', InformationSchemaTables::STATISTICS->value);
        $this->assertEquals('COLUMN_STATISTICS', InformationSchemaTables::COLUMN_STATISTICS->value);
        $this->assertEquals('OPTIMIZER_TRACE', InformationSchemaTables::OPTIMIZER_TRACE->value);
    }

    public function testExtensionsTables(): void
    {
        $this->assertEquals('COLUMNS_EXTENSIONS', InformationSchemaTables::COLUMNS_EXTENSIONS->value);
        $this->assertEquals('TABLES_EXTENSIONS', InformationSchemaTables::TABLES_EXTENSIONS->value);
        $this->assertEquals('TABLESPACES_EXTENSIONS', InformationSchemaTables::TABLESPACES_EXTENSIONS->value);
    }

    public function testSpatialTables(): void
    {
        $this->assertEquals('ST_GEOMETRY_COLUMNS', InformationSchemaTables::ST_GEOMETRY_COLUMNS->value);
        $this->assertEquals('ST_SPATIAL_REFERENCE_SYSTEMS', InformationSchemaTables::ST_SPATIAL_REFERENCE_SYSTEMS->value);
        $this->assertEquals('ST_UNITS_OF_MEASURE', InformationSchemaTables::ST_UNITS_OF_MEASURE->value);
    }

    public function testUserTables(): void
    {
        $this->assertEquals('USER_PRIVILEGES', InformationSchemaTables::USER_PRIVILEGES->value);
        $this->assertEquals('USER_ATTRIBUTES', InformationSchemaTables::USER_ATTRIBUTES->value);
        $this->assertEquals('SCHEMA_PRIVILEGES', InformationSchemaTables::SCHEMA_PRIVILEGES->value);
    }

    public function testPartitionsAndTablespaces(): void
    {
        $this->assertEquals('PARTITIONS', InformationSchemaTables::PARTITIONS->value);
        $this->assertEquals('TABLESPACES', InformationSchemaTables::TABLESPACES->value);
        $this->assertEquals('RESOURCE_GROUPS', InformationSchemaTables::RESOURCE_GROUPS->value);
    }

    public function testSpecialCases(): void
    {
        // Test case with different formatting
        $this->assertEquals('ndb_transit_mysql_connection_map', InformationSchemaTables::NDB_TRANSIT_MYSQL_CONNECTION_MAP->value);
    }

    public function testAllEnumCasesExist(): void
    {
        $cases = InformationSchemaTables::cases();
        
        // Should have all the enum cases defined (current count is 79)
        $this->assertCount(79, $cases);
        
        // Test some key ones exist
        $caseValues = array_map(fn($case) => $case->value, $cases);
        
        $this->assertContains('TABLES', $caseValues);
        $this->assertContains('COLUMNS', $caseValues);
        $this->assertContains('KEY_COLUMN_USAGE', $caseValues);
        $this->assertContains('REFERENTIAL_CONSTRAINTS', $caseValues);
        $this->assertContains('INNODB_TABLES', $caseValues);
        $this->assertContains('VIEWS', $caseValues);
    }

    public function testEnumInstancesAreUnique(): void
    {
        $cases = InformationSchemaTables::cases();
        $values = array_map(fn($case) => $case->value, $cases);
        
        // All values should be unique
        $this->assertEquals(count($values), count(array_unique($values)));
    }

    public function testEnumFromValue(): void
    {
        // Test that we can get enum instances from values
        $this->assertEquals(InformationSchemaTables::TABLES, InformationSchemaTables::from('TABLES'));
        $this->assertEquals(InformationSchemaTables::COLUMNS, InformationSchemaTables::from('COLUMNS'));
        $this->assertEquals(InformationSchemaTables::INNODB_TABLES, InformationSchemaTables::from('INNODB_TABLES'));
    }

    public function testEnumTryFromValue(): void
    {
        // Test that tryFrom works correctly
        $this->assertEquals(InformationSchemaTables::TABLES, InformationSchemaTables::tryFrom('TABLES'));
        $this->assertEquals(InformationSchemaTables::COLUMNS, InformationSchemaTables::tryFrom('COLUMNS'));
        $this->assertNull(InformationSchemaTables::tryFrom('NON_EXISTENT_TABLE'));
    }

    public function testAllCasesHaveStringValues(): void
    {
        $cases = InformationSchemaTables::cases();
        
        foreach ($cases as $case) {
            $this->assertIsString($case->value);
            $this->assertNotEmpty($case->value);
        }
    }

    public function testCaseNamesMatchValues(): void
    {
        // Most cases should have names that match their values
        $specialCases = ['NDB_TRANSIT_MYSQL_CONNECTION_MAP']; // Exception case
        
        $cases = InformationSchemaTables::cases();
        
        foreach ($cases as $case) {
            if (!in_array($case->name, $specialCases, true)) {
                $this->assertEquals($case->name, $case->value, 
                    "Case {$case->name} should have value {$case->name} but has {$case->value}");
            }
        }
    }
}