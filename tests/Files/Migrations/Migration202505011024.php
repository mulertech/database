<?php

use MulerTech\Database\Schema\Builder\SchemaBuilder;
use MulerTech\Database\Schema\Migration\Migration;

/**
 * Auto-generated migration
 */
class Migration202505011024 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $schema = new SchemaBuilder();
        $tableDefinition = $schema->createTable("groups_test");
        $tableDefinition->column("id")->integer()->unsigned()->notNull()->autoIncrement();
        $tableDefinition->primaryKey("id");
        $tableDefinition->column("name")->string(255)->notNull();
        $tableDefinition->column("description")->text();
        $tableDefinition->column("created_at")->timestamp();
        $tableDefinition->column("member_count")->mediumInt()->unsigned()->default("0");
        $tableDefinition->column("parent_id")->integer()->unsigned();
        $tableDefinition->engine("InnoDB")
            ->charset("utf8mb4")
            ->collation("utf8mb4_unicode_ci");
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->createTable("link_user_group_test");
        $tableDefinition->column("id")->integer()->unsigned()->notNull()->autoIncrement();
        $tableDefinition->primaryKey("id");
        $tableDefinition->column("user_id")->integer()->unsigned()->notNull();
        $tableDefinition->column("group_id")->integer()->unsigned()->notNull();
        $tableDefinition->engine("InnoDB")
            ->charset("utf8mb4")
            ->collation("utf8mb4_unicode_ci");
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->createTable("same_table_name");
        $tableDefinition->column("id")->integer()->unsigned()->notNull()->autoIncrement();
        $tableDefinition->primaryKey("id");
        $tableDefinition->engine("InnoDB")
            ->charset("utf8mb4")
            ->collation("utf8mb4_unicode_ci");
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->createTable("group_sub");
        $tableDefinition->column("id")->integer()->unsigned()->notNull()->autoIncrement();
        $tableDefinition->primaryKey("id");
        $tableDefinition->engine("InnoDB")
            ->charset("utf8mb4")
            ->collation("utf8mb4_unicode_ci");
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->createTable("units_test");
        $tableDefinition->column("id")->integer()->unsigned()->notNull()->autoIncrement();
        $tableDefinition->primaryKey("id");
        $tableDefinition->column("name")->string(255)->notNull();
        $tableDefinition->column("unit_code")->char(10);
        $tableDefinition->column("priority")->tinyInt()->unsigned()->default("1");
        $tableDefinition->column("is_enabled")->tinyInt(1)->notNull()->default(1);
        $tableDefinition->engine("InnoDB")
            ->charset("utf8mb4")
            ->collation("utf8mb4_unicode_ci");
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->createTable("users_test");
        $tableDefinition->column("id")->integer()->unsigned()->notNull()->autoIncrement();
        $tableDefinition->primaryKey("id");
        $tableDefinition->column("username")->string(255)->notNull()->default("John");
        $tableDefinition->column("size")->integer();
        $tableDefinition->column("account_balance")->float(10, 2);
        $tableDefinition->column("unit_id")->integer()->unsigned()->notNull();
        $tableDefinition->column("age")->tinyInt()->unsigned();
        $tableDefinition->column("score")->smallInt();
        $tableDefinition->column("views")->mediumInt()->unsigned();
        $tableDefinition->column("big_number")->bigInteger()->unsigned();
        $tableDefinition->column("decimal_value")->decimal(10, 2);
        $tableDefinition->column("double_value")->double();
        $tableDefinition->column("char_code")->char(5);
        $tableDefinition->column("description")->text();
        $tableDefinition->column("tiny_text")->tinyText();
        $tableDefinition->column("medium_text")->mediumText();
        $tableDefinition->column("long_text")->longText();
        $tableDefinition->column("binary_data")->binary(16);
        $tableDefinition->column("varbinary_data")->varbinary(255);
        $tableDefinition->column("blob_data")->blob();
        $tableDefinition->column("tiny_blob")->tinyBlob();
        $tableDefinition->column("medium_blob")->mediumBlob();
        $tableDefinition->column("long_blob")->longBlob();
        $tableDefinition->column("birth_date")->date();
        $tableDefinition->column("created_at")->datetime();
        $tableDefinition->column("updated_at")->timestamp();
        $tableDefinition->column("work_time")->time();
        $tableDefinition->column("birth_year")->year();
        $tableDefinition->column("is_active")->tinyInt(1)->default(0);
        $tableDefinition->column("is_verified")->tinyInt(1);
        $tableDefinition->column("status")->enum(['active', 'inactive', 'pending', 'banned']);
        $tableDefinition->column("permissions")->set(['read', 'write', 'delete', 'update']);
        $tableDefinition->column("metadata")->json();
        $tableDefinition->column("location")->geometry();
        $tableDefinition->column("coordinates")->point();
        $tableDefinition->column("path")->linestring();
        $tableDefinition->column("area")->polygon();
        $tableDefinition->column("manager")->integer()->unsigned();
        $tableDefinition->engine("InnoDB")
            ->charset("utf8mb4")
            ->collation("utf8mb4_unicode_ci");
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("groups_test");
        $tableDefinition->foreignKey("fk_groups_test_parent_id_groups_test")
            ->columns("parent_id")
            ->references("groups_test", "id")
            ->onDelete(\MulerTech\Database\Schema\Types\ReferentialAction::CASCADE)
            ->onUpdate(\MulerTech\Database\Schema\Types\ReferentialAction::CASCADE);
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("link_user_group_test");
        $tableDefinition->foreignKey("fk_link_user_group_test_user_id_users_test")
            ->columns("user_id")
            ->references("users_test", "id")
            ->onDelete(\MulerTech\Database\Schema\Types\ReferentialAction::CASCADE)
            ->onUpdate(\MulerTech\Database\Schema\Types\ReferentialAction::CASCADE);
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("link_user_group_test");
        $tableDefinition->foreignKey("fk_link_user_group_test_group_id_groups_test")
            ->columns("group_id")
            ->references("groups_test", "id")
            ->onDelete(\MulerTech\Database\Schema\Types\ReferentialAction::CASCADE)
            ->onUpdate(\MulerTech\Database\Schema\Types\ReferentialAction::CASCADE);
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("users_test");
        $tableDefinition->foreignKey("fk_users_test_unit_id_units_test")
            ->columns("unit_id")
            ->references("units_test", "id")
            ->onDelete(\MulerTech\Database\Schema\Types\ReferentialAction::RESTRICT)
            ->onUpdate(\MulerTech\Database\Schema\Types\ReferentialAction::CASCADE);
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("users_test");
        $tableDefinition->foreignKey("fk_users_test_manager_users_test")
            ->columns("manager")
            ->references("users_test", "id")
            ->onDelete(\MulerTech\Database\Schema\Types\ReferentialAction::SET_NULL)
            ->onUpdate(\MulerTech\Database\Schema\Types\ReferentialAction::CASCADE);
        $sql = $tableDefinition->toSql();
        $this->entityManager->getPdm()->exec($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("groups_test");
        $tableDefinition->dropForeignKey("fk_groups_test_parent_id_groups_test");
        $this->entityManager->getPdm()->exec($tableDefinition->toSql());

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("link_user_group_test");
        $tableDefinition->dropForeignKey("fk_link_user_group_test_user_id_users_test");
        $this->entityManager->getPdm()->exec($tableDefinition->toSql());

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("link_user_group_test");
        $tableDefinition->dropForeignKey("fk_link_user_group_test_group_id_groups_test");
        $this->entityManager->getPdm()->exec($tableDefinition->toSql());

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("users_test");
        $tableDefinition->dropForeignKey("fk_users_test_unit_id_units_test");
        $this->entityManager->getPdm()->exec($tableDefinition->toSql());

        $schema = new SchemaBuilder();
        $tableDefinition = $schema->alterTable("users_test");
        $tableDefinition->dropForeignKey("fk_users_test_manager_users_test");
        $this->entityManager->getPdm()->exec($tableDefinition->toSql());

        $schema = new SchemaBuilder();
        $sql = $schema->dropTable("groups_test");
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $sql = $schema->dropTable("link_user_group_test");
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $sql = $schema->dropTable("same_table_name");
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $sql = $schema->dropTable("group_sub");
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $sql = $schema->dropTable("units_test");
        $this->entityManager->getPdm()->exec($sql);

        $schema = new SchemaBuilder();
        $sql = $schema->dropTable("users_test");
        $this->entityManager->getPdm()->exec($sql);
    }
}