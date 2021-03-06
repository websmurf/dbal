<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use function array_shift;

abstract class AbstractMySQLPlatformTestCase extends AbstractPlatformTestCase
{
    public function testModifyLimitQueryWitoutLimit()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT n FROM Foo', null , 10);
        self::assertEquals('SELECT n FROM Foo LIMIT 18446744073709551615 OFFSET 10',$sql);
    }

    public function testGenerateMixedCaseTableCreate()
    {
        $table = new Table("Foo");
        $table->addColumn("Bar", "integer");

        $sql = $this->_platform->getCreateTableSQL($table);
        self::assertEquals('CREATE TABLE Foo (Bar INT NOT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB', array_shift($sql));
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INT AUTO_INCREMENT NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA (foo, bar)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            "ALTER TABLE mytable RENAME TO userlist, ADD quota INT DEFAULT NULL, DROP foo, CHANGE bar baz VARCHAR(255) DEFAULT 'def' NOT NULL, CHANGE bloo bloo TINYINT(1) DEFAULT '0' NOT NULL"
        );
    }

    public function testGeneratesSqlSnippets()
    {
        self::assertEquals('RLIKE', $this->_platform->getRegexpExpression(), 'Regular expression operator is not correct');
        self::assertEquals('`', $this->_platform->getIdentifierQuoteCharacter(), 'Quote character is not correct');
        self::assertEquals('CONCAT(column1, column2, column3)', $this->_platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation function is not correct');
    }

    public function testGeneratesTransactionsCommands()
    {
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED),
            ''
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED)
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ)
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE)
        );
    }


    public function testGeneratesDDLSnippets()
    {
        self::assertEquals('SHOW DATABASES', $this->_platform->getListDatabasesSQL());
        self::assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals('DROP DATABASE foobar', $this->_platform->getDropDatabaseSQL('foobar'));
        self::assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        self::assertEquals(
            'INT',
            $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        self::assertEquals(
            'INT AUTO_INCREMENT',
            $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true)
        ));
        self::assertEquals(
            'INT AUTO_INCREMENT',
            $this->_platform->getIntegerTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true)
        ));
    }

    public function testGeneratesTypeDeclarationForStrings()
    {
        self::assertEquals(
            'CHAR(10)',
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('length' => 10, 'fixed' => true)
        ));
        self::assertEquals(
            'VARCHAR(50)',
            $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 50)),
            'Variable string declaration is not correct'
        );
        self::assertEquals(
            'VARCHAR(255)',
            $this->_platform->getVarcharTypeDeclarationSQL(array()),
            'Long string declaration is not correct'
        );
    }

    public function testPrefersIdentityColumns()
    {
        self::assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        self::assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testDoesSupportSavePoints()
    {
        self::assertTrue($this->_platform->supportsSavepoints());
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    /**
     * @group DBAL-126
     */
    public function testUniquePrimaryKey()
    {
        $keyTable = new Table("foo");
        $keyTable->addColumn("bar", "integer");
        $keyTable->addColumn("baz", "string");
        $keyTable->setPrimaryKey(array("bar"));
        $keyTable->addUniqueIndex(array("baz"));

        $oldTable = new Table("foo");
        $oldTable->addColumn("bar", "integer");
        $oldTable->addColumn("baz", "string");

        $c = new \Doctrine\DBAL\Schema\Comparator;
        $diff = $c->diffTable($oldTable, $keyTable);

        $sql = $this->_platform->getAlterTableSQL($diff);

        self::assertEquals(array(
            "ALTER TABLE foo ADD PRIMARY KEY (bar)",
            "CREATE UNIQUE INDEX UNIQ_8C73652178240498 ON foo (baz)",
        ), $sql);
    }

    public function testModifyLimitQuery()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    /**
     * @group DDC-118
     */
    public function testGetDateTimeTypeDeclarationSql()
    {
        self::assertEquals("DATETIME", $this->_platform->getDateTimeTypeDeclarationSQL(array('version' => false)));
        self::assertEquals("TIMESTAMP", $this->_platform->getDateTimeTypeDeclarationSQL(array('version' => true)));
        self::assertEquals("DATETIME", $this->_platform->getDateTimeTypeDeclarationSQL(array()));
    }

    public function getCreateTableColumnCommentsSQL()
    {
        return array("CREATE TABLE test (id INT NOT NULL COMMENT 'This is a comment', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB");
    }

    public function getAlterTableColumnCommentsSQL()
    {
        return array("ALTER TABLE mytable ADD quota INT NOT NULL COMMENT 'A comment', CHANGE foo foo VARCHAR(255) NOT NULL, CHANGE bar baz VARCHAR(255) NOT NULL COMMENT 'B comment'");
    }

    public function getCreateTableColumnTypeCommentsSQL()
    {
        return array("CREATE TABLE test (id INT NOT NULL, data LONGTEXT NOT NULL COMMENT '(DC2Type:array)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB");
    }

    /**
     * @group DBAL-237
     */
    public function testChangeIndexWithForeignKeys()
    {
        $index = new Index("idx", array("col"), false);
        $unique = new Index("uniq", array("col"), true);

        $diff = new TableDiff("test", array(), array(), array(), array($unique), array(), array($index));
        $sql = $this->_platform->getAlterTableSQL($diff);
        self::assertEquals(array("ALTER TABLE test DROP INDEX idx, ADD UNIQUE INDEX uniq (col)"), $sql);

        $diff = new TableDiff("test", array(), array(), array(), array($index), array(), array($unique));
        $sql = $this->_platform->getAlterTableSQL($diff);
        self::assertEquals(array("ALTER TABLE test DROP INDEX uniq, ADD INDEX idx (col)"), $sql);
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, PRIMARY KEY(`create`)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, INDEX IDX_22660D028FD6E0FB (`create`)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
    }

    protected function getQuotedNameInIndexSQL()
    {
        return array(
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL, INDEX `key` (column1)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, `bar` VARCHAR(255) NOT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY (`create`, foo, `bar`) REFERENCES `foreign` (`create`, bar, `foo-bar`)',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY (`create`, foo, `bar`) REFERENCES foo (`create`, bar, `foo-bar`)',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY (`create`, foo, `bar`) REFERENCES `foo-bar` (`create`, bar, `foo-bar`)',
        );
    }

    public function testCreateTableWithFulltextIndex()
    {
        $table = new Table('fulltext_table');
        $table->addOption('engine', 'MyISAM');
        $table->addColumn('text', 'text');
        $table->addIndex(array('text'), 'fulltext_text');

        $index = $table->getIndex('fulltext_text');
        $index->addFlag('fulltext');

        $sql = $this->_platform->getCreateTableSQL($table);
        self::assertEquals(array('CREATE TABLE fulltext_table (text LONGTEXT NOT NULL, FULLTEXT INDEX fulltext_text (text)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = MyISAM'), $sql);
    }

    public function testCreateTableWithSpatialIndex()
    {
        $table = new Table('spatial_table');
        $table->addOption('engine', 'MyISAM');
        $table->addColumn('point', 'text'); // This should be a point type
        $table->addIndex(array('point'), 'spatial_text');

        $index = $table->getIndex('spatial_text');
        $index->addFlag('spatial');

        $sql = $this->_platform->getCreateTableSQL($table);
        self::assertEquals(array('CREATE TABLE spatial_table (point LONGTEXT NOT NULL, SPATIAL INDEX spatial_text (point)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = MyISAM'), $sql);
    }

    public function testClobTypeDeclarationSQL()
    {
        self::assertEquals('TINYTEXT', $this->_platform->getClobTypeDeclarationSQL(array('length' => 1)));
        self::assertEquals('TINYTEXT', $this->_platform->getClobTypeDeclarationSQL(array('length' => 255)));
        self::assertEquals('TEXT', $this->_platform->getClobTypeDeclarationSQL(array('length' => 256)));
        self::assertEquals('TEXT', $this->_platform->getClobTypeDeclarationSQL(array('length' => 65535)));
        self::assertEquals('MEDIUMTEXT', $this->_platform->getClobTypeDeclarationSQL(array('length' => 65536)));
        self::assertEquals('MEDIUMTEXT', $this->_platform->getClobTypeDeclarationSQL(array('length' => 16777215)));
        self::assertEquals('LONGTEXT', $this->_platform->getClobTypeDeclarationSQL(array('length' => 16777216)));
        self::assertEquals('LONGTEXT', $this->_platform->getClobTypeDeclarationSQL(array()));
    }

    public function testBlobTypeDeclarationSQL()
    {
        self::assertEquals('TINYBLOB', $this->_platform->getBlobTypeDeclarationSQL(array('length' => 1)));
        self::assertEquals('TINYBLOB', $this->_platform->getBlobTypeDeclarationSQL(array('length' => 255)));
        self::assertEquals('BLOB', $this->_platform->getBlobTypeDeclarationSQL(array('length' => 256)));
        self::assertEquals('BLOB', $this->_platform->getBlobTypeDeclarationSQL(array('length' => 65535)));
        self::assertEquals('MEDIUMBLOB', $this->_platform->getBlobTypeDeclarationSQL(array('length' => 65536)));
        self::assertEquals('MEDIUMBLOB', $this->_platform->getBlobTypeDeclarationSQL(array('length' => 16777215)));
        self::assertEquals('LONGBLOB', $this->_platform->getBlobTypeDeclarationSQL(array('length' => 16777216)));
        self::assertEquals('LONGBLOB', $this->_platform->getBlobTypeDeclarationSQL(array()));
    }

    /**
     * @group DBAL-400
     */
    public function testAlterTableAddPrimaryKey()
    {
        $table = new Table('alter_table_add_pk');
        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addIndex(array('id'), 'idx_id');

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->dropIndex('idx_id');
        $diffTable->setPrimaryKey(array('id'));

        self::assertEquals(
            array('DROP INDEX idx_id ON alter_table_add_pk', 'ALTER TABLE alter_table_add_pk ADD PRIMARY KEY (id)'),
            $this->_platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-1132
     */
    public function testAlterPrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table("alter_primary_key");
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('foo', 'integer');
        $table->setPrimaryKey(array('id'));

        $comparator = new Comparator();
        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(array('foo'));

        self::assertEquals(
            array(
                'ALTER TABLE alter_primary_key MODIFY id INT NOT NULL',
                'ALTER TABLE alter_primary_key DROP PRIMARY KEY',
                'ALTER TABLE alter_primary_key ADD PRIMARY KEY (foo)'
            ),
            $this->_platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-464
     */
    public function testDropPrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table("drop_primary_key");
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'integer');
        $table->setPrimaryKey(array('id', 'foo'));

        $comparator = new Comparator();
        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();

        self::assertEquals(
            array(
                'ALTER TABLE drop_primary_key MODIFY id INT NOT NULL',
                'ALTER TABLE drop_primary_key DROP PRIMARY KEY'
            ),
            $this->_platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-2302
     */
    public function testDropNonAutoincrementColumnFromCompositePrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table("tbl");
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'integer');
        $table->setPrimaryKey(array('id', 'foo'));

        $comparator = new Comparator();
        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(array('id'));

        self::assertSame(
            array(
                'ALTER TABLE tbl MODIFY id INT NOT NULL',
                'ALTER TABLE tbl DROP PRIMARY KEY',
                'ALTER TABLE tbl ADD PRIMARY KEY (id)',
            ),
            $this->_platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-2302
     */
    public function testAddNonAutoincrementColumnToPrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table("tbl");
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'integer');
        $table->setPrimaryKey(array('id'));

        $comparator = new Comparator();
        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(array('id', 'foo'));

        self::assertSame(
            array(
                'ALTER TABLE tbl MODIFY id INT NOT NULL',
                'ALTER TABLE tbl DROP PRIMARY KEY',
                'ALTER TABLE tbl ADD PRIMARY KEY (id, foo)',
            ),
            $this->_platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-586
     */
    public function testAddAutoIncrementPrimaryKey()
    {
        $keyTable = new Table("foo");
        $keyTable->addColumn("id", "integer", array('autoincrement' => true));
        $keyTable->addColumn("baz", "string");
        $keyTable->setPrimaryKey(array("id"));

        $oldTable = new Table("foo");
        $oldTable->addColumn("baz", "string");

        $c = new \Doctrine\DBAL\Schema\Comparator;
        $diff = $c->diffTable($oldTable, $keyTable);

        $sql = $this->_platform->getAlterTableSQL($diff);

        self::assertEquals(array(
            "ALTER TABLE foo ADD id INT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id)",
        ), $sql);
    }

    public function testNamedPrimaryKey()
    {
        $diff = new TableDiff('mytable');
        $diff->changedIndexes['foo_index'] = new Index('foo_index', array('foo'), true, true);

        $sql = $this->_platform->getAlterTableSQL($diff);

        self::assertEquals(array(
            "ALTER TABLE mytable DROP PRIMARY KEY",
            "ALTER TABLE mytable ADD PRIMARY KEY (foo)",
        ), $sql);
    }

    public function testAlterPrimaryKeyWithNewColumn()
    {
        $table = new Table("yolo");
        $table->addColumn('pkc1', 'integer');
        $table->addColumn('col_a', 'integer');
        $table->setPrimaryKey(array('pkc1'));

        $comparator = new Comparator();
        $diffTable = clone $table;

        $diffTable->addColumn('pkc2', 'integer');
        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(array('pkc1', 'pkc2'));

        self::assertSame(
            array(
                'ALTER TABLE yolo DROP PRIMARY KEY',
                'ALTER TABLE yolo ADD pkc2 INT NOT NULL',
                'ALTER TABLE yolo ADD PRIMARY KEY (pkc1, pkc2)',
            ),
            $this->_platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    public function testInitializesDoctrineTypeMappings()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('binary'));
        self::assertSame('binary', $this->_platform->getDoctrineTypeMapping('binary'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('varbinary'));
        self::assertSame('binary', $this->_platform->getDoctrineTypeMapping('varbinary'));
    }

    protected function getBinaryMaxLength()
    {
        return 65535;
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        self::assertSame('VARBINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        self::assertSame('VARBINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        self::assertSame('VARBINARY(65535)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 65535)));

        self::assertSame('BINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        self::assertSame('BINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        self::assertSame('BINARY(65535)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 65535)));
    }

    /**
     * @group legacy
     * @expectedDeprecation Binary field length 65536 is greater than supported by the platform (65535). Reduce the field length or use a BLOB field instead.
     * @expectedDeprecation Binary field length 16777215 is greater than supported by the platform (65535). Reduce the field length or use a BLOB field instead.
     * @expectedDeprecation Binary field length 16777216 is greater than supported by the platform (65535). Reduce the field length or use a BLOB field instead.
     */
    public function testReturnsBinaryTypeLongerThanMaxDeclarationSQL()
    {
        self::assertSame('MEDIUMBLOB', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 65536]));
        self::assertSame('MEDIUMBLOB', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 16777215]));
        self::assertSame('LONGBLOB', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 16777216]));

        self::assertSame('MEDIUMBLOB', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 65536]));
        self::assertSame('MEDIUMBLOB', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 16777215]));
        self::assertSame('LONGBLOB', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 16777216]));
    }

    public function testDoesNotPropagateForeignKeyCreationForNonSupportingEngines()
    {
        $table = new Table("foreign_table");
        $table->addColumn('id', 'integer');
        $table->addColumn('fk_id', 'integer');
        $table->addForeignKeyConstraint('foreign_table', array('fk_id'), array('id'));
        $table->setPrimaryKey(array('id'));
        $table->addOption('engine', 'MyISAM');

        self::assertSame(
            array('CREATE TABLE foreign_table (id INT NOT NULL, fk_id INT NOT NULL, INDEX IDX_5690FFE2A57719D0 (fk_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = MyISAM'),
            $this->_platform->getCreateTableSQL(
                $table,
                AbstractPlatform::CREATE_INDEXES|AbstractPlatform::CREATE_FOREIGNKEYS
            )
        );

        $table = clone $table;
        $table->addOption('engine', 'InnoDB');

        self::assertSame(
            array(
                'CREATE TABLE foreign_table (id INT NOT NULL, fk_id INT NOT NULL, INDEX IDX_5690FFE2A57719D0 (fk_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB',
                'ALTER TABLE foreign_table ADD CONSTRAINT FK_5690FFE2A57719D0 FOREIGN KEY (fk_id) REFERENCES foreign_table (id)'
            ),
            $this->_platform->getCreateTableSQL(
                $table,
                AbstractPlatform::CREATE_INDEXES|AbstractPlatform::CREATE_FOREIGNKEYS
            )
        );
    }

    public function testDoesNotPropagateForeignKeyAlterationForNonSupportingEngines()
    {
        $table = new Table("foreign_table");
        $table->addColumn('id', 'integer');
        $table->addColumn('fk_id', 'integer');
        $table->addForeignKeyConstraint('foreign_table', array('fk_id'), array('id'));
        $table->setPrimaryKey(array('id'));
        $table->addOption('engine', 'MyISAM');

        $addedForeignKeys   = array(new ForeignKeyConstraint(array('fk_id'), 'foo', array('id'), 'fk_add'));
        $changedForeignKeys = array(new ForeignKeyConstraint(array('fk_id'), 'bar', array('id'), 'fk_change'));
        $removedForeignKeys = array(new ForeignKeyConstraint(array('fk_id'), 'baz', array('id'), 'fk_remove'));

        $tableDiff = new TableDiff('foreign_table');
        $tableDiff->fromTable = $table;
        $tableDiff->addedForeignKeys = $addedForeignKeys;
        $tableDiff->changedForeignKeys = $changedForeignKeys;
        $tableDiff->removedForeignKeys = $removedForeignKeys;

        self::assertEmpty($this->_platform->getAlterTableSQL($tableDiff));

        $table->addOption('engine', 'InnoDB');

        $tableDiff = new TableDiff('foreign_table');
        $tableDiff->fromTable = $table;
        $tableDiff->addedForeignKeys = $addedForeignKeys;
        $tableDiff->changedForeignKeys = $changedForeignKeys;
        $tableDiff->removedForeignKeys = $removedForeignKeys;

        self::assertSame(
            array(
                'ALTER TABLE foreign_table DROP FOREIGN KEY fk_remove',
                'ALTER TABLE foreign_table DROP FOREIGN KEY fk_change',
                'ALTER TABLE foreign_table ADD CONSTRAINT fk_add FOREIGN KEY (fk_id) REFERENCES foo (id)',
                'ALTER TABLE foreign_table ADD CONSTRAINT fk_change FOREIGN KEY (fk_id) REFERENCES bar (id)',
            ),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            'DROP INDEX idx_foo ON mytable',
            'CREATE INDEX idx_bar ON mytable (id)',
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            'DROP INDEX `create` ON `table`',
            'CREATE INDEX `select` ON `table` (id)',
            'DROP INDEX `foo` ON `table`',
            'CREATE INDEX `bar` ON `table` (id)',
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'DROP INDEX idx_foo ON myschema.mytable',
            'CREATE INDEX idx_bar ON myschema.mytable (id)',
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'DROP INDEX `create` ON `schema`.`table`',
            'CREATE INDEX `select` ON `schema`.`table` (id)',
            'DROP INDEX `foo` ON `schema`.`table`',
            'CREATE INDEX `bar` ON `schema`.`table` (id)',
        );
    }

    protected function getQuotesDropForeignKeySQL()
    {
        return 'ALTER TABLE `table` DROP FOREIGN KEY `select`';
    }

    protected function getQuotesDropConstraintSQL()
    {
        return 'ALTER TABLE `table` DROP CONSTRAINT `select`';
    }

    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes()
    {
        $table = new Table("text_blob_default_value");
        $table->addColumn('def_text', 'text', array('default' => 'def'));
        $table->addColumn('def_text_null', 'text', array('notnull' => false, 'default' => 'def'));
        $table->addColumn('def_blob', 'blob', array('default' => 'def'));
        $table->addColumn('def_blob_null', 'blob', array('notnull' => false, 'default' => 'def'));

        self::assertSame(
            array('CREATE TABLE text_blob_default_value (def_text LONGTEXT NOT NULL, def_text_null LONGTEXT DEFAULT NULL, def_blob LONGBLOB NOT NULL, def_blob_null LONGBLOB DEFAULT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'),
            $this->_platform->getCreateTableSQL($table)
        );

        $diffTable = clone $table;
        $diffTable->changeColumn('def_text', array('default' => null));
        $diffTable->changeColumn('def_text_null', array('default' => null));
        $diffTable->changeColumn('def_blob', array('default' => null));
        $diffTable->changeColumn('def_blob_null', array('default' => null));

        $comparator = new Comparator();

        self::assertEmpty($this->_platform->getAlterTableSQL($comparator->diffTable($table, $diffTable)));
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL()
    {
        return array(
            "ALTER TABLE mytable " .
            "CHANGE unquoted1 unquoted INT NOT NULL COMMENT 'Unquoted 1', " .
            "CHANGE unquoted2 `where` INT NOT NULL COMMENT 'Unquoted 2', " .
            "CHANGE unquoted3 `foo` INT NOT NULL COMMENT 'Unquoted 3', " .
            "CHANGE `create` reserved_keyword INT NOT NULL COMMENT 'Reserved keyword 1', " .
            "CHANGE `table` `from` INT NOT NULL COMMENT 'Reserved keyword 2', " .
            "CHANGE `select` `bar` INT NOT NULL COMMENT 'Reserved keyword 3', " .
            "CHANGE quoted1 quoted INT NOT NULL COMMENT 'Quoted 1', " .
            "CHANGE quoted2 `and` INT NOT NULL COMMENT 'Quoted 2', " .
            "CHANGE quoted3 `baz` INT NOT NULL COMMENT 'Quoted 3'"
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL()
    {
        return array(
            "ALTER TABLE mytable " .
            "CHANGE unquoted1 unquoted1 VARCHAR(255) NOT NULL COMMENT 'Unquoted 1', " .
            "CHANGE unquoted2 unquoted2 VARCHAR(255) NOT NULL COMMENT 'Unquoted 2', " .
            "CHANGE unquoted3 unquoted3 VARCHAR(255) NOT NULL COMMENT 'Unquoted 3', " .
            "CHANGE `create` `create` VARCHAR(255) NOT NULL COMMENT 'Reserved keyword 1', " .
            "CHANGE `table` `table` VARCHAR(255) NOT NULL COMMENT 'Reserved keyword 2', " .
            "CHANGE `select` `select` VARCHAR(255) NOT NULL COMMENT 'Reserved keyword 3'"
        );
    }

    /**
     * @group DBAL-423
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        self::assertSame('CHAR(36)', $this->_platform->getGuidTypeDeclarationSQL(array()));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableRenameColumnSQL()
    {
        return array(
            "ALTER TABLE foo CHANGE bar baz INT DEFAULT 666 NOT NULL COMMENT 'rename test'",
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL()
    {
        return array(
            'ALTER TABLE `foo` DROP FOREIGN KEY fk1',
            'ALTER TABLE `foo` DROP FOREIGN KEY fk2',
            'ALTER TABLE `foo` RENAME TO `table`, ADD bloo INT NOT NULL, DROP baz, CHANGE bar bar INT DEFAULT NULL, ' .
            'CHANGE id war INT NOT NULL',
            'ALTER TABLE `table` ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id)',
            'ALTER TABLE `table` ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id)',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommentOnColumnSQL()
    {
        return array(
            "COMMENT ON COLUMN foo.bar IS 'comment'",
            "COMMENT ON COLUMN `Foo`.`BAR` IS 'comment'",
            "COMMENT ON COLUMN `select`.`from` IS 'comment'",
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        return 'CONSTRAINT `select` UNIQUE (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInIndexDeclarationSQL()
    {
        return 'INDEX `select` (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInTruncateTableSQL()
    {
        return 'TRUNCATE `select`';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL()
    {
        return array(
            'ALTER TABLE mytable CHANGE name name CHAR(2) NOT NULL',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return array(
            'ALTER TABLE mytable DROP FOREIGN KEY fk_foo',
            'DROP INDEX idx_foo ON mytable',
            'CREATE INDEX idx_foo_renamed ON mytable (foo)',
            'ALTER TABLE mytable ADD CONSTRAINT fk_foo FOREIGN KEY (foo) REFERENCES foreign_table (id)',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getGeneratesDecimalTypeDeclarationSQL()
    {
        return array(
            array(array(), 'NUMERIC(10, 0)'),
            array(array('unsigned' => true), 'NUMERIC(10, 0) UNSIGNED'),
            array(array('unsigned' => false), 'NUMERIC(10, 0)'),
            array(array('precision' => 5), 'NUMERIC(5, 0)'),
            array(array('scale' => 5), 'NUMERIC(10, 5)'),
            array(array('precision' => 8, 'scale' => 2), 'NUMERIC(8, 2)'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getGeneratesFloatDeclarationSQL()
    {
        return array(
            array(array(), 'DOUBLE PRECISION'),
            array(array('unsigned' => true), 'DOUBLE PRECISION UNSIGNED'),
            array(array('unsigned' => false), 'DOUBLE PRECISION'),
            array(array('precision' => 5), 'DOUBLE PRECISION'),
            array(array('scale' => 5), 'DOUBLE PRECISION'),
            array(array('precision' => 8, 'scale' => 2), 'DOUBLE PRECISION'),
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableIndexesSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableIndexesSQL("Foo'Bar\\", 'foo_db'), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListTableIndexesSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableIndexesSQL('foo_table', "Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListViewsSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListViewsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableForeignKeysSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableForeignKeysSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListTableForeignKeysSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableForeignKeysSQL('foo_table', "Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableColumnsSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableColumnsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListTableColumnsSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableColumnsSQL('foo_table', "Foo'Bar\\"), '', true);
    }

    public function testListTableForeignKeysSQLEvaluatesDatabase()
    {
        $sql = $this->_platform->getListTableForeignKeysSQL('foo');

        self::assertContains('DATABASE()', $sql);

        $sql = $this->_platform->getListTableForeignKeysSQL('foo', 'bar');

        self::assertContains('bar', $sql);
        self::assertNotContains('DATABASE()', $sql);
    }

    public function testSupportsColumnCollation() : void
    {
        self::assertTrue($this->_platform->supportsColumnCollation());
    }

    public function testColumnCollationDeclarationSQL() : void
    {
        self::assertSame(
            'COLLATE ascii_general_ci',
            $this->_platform->getColumnCollationDeclarationSQL('ascii_general_ci')
        );
    }

    public function testGetCreateTableSQLWithColumnCollation() : void
    {
        $table = new Table('foo');
        $table->addColumn('no_collation', 'string');
        $table->addColumn('column_collation', 'string')->setPlatformOption('collation', 'ascii_general_ci');

        self::assertSame(
            ['CREATE TABLE foo (no_collation VARCHAR(255) NOT NULL, column_collation VARCHAR(255) NOT NULL COLLATE ascii_general_ci) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'],
            $this->_platform->getCreateTableSQL($table),
            'Column "no_collation" will use the default collation from the table/database and "column_collation" overwrites the collation on this column'
        );
    }

    /**
     * @group DBAL-2576
     */
    protected function createNotDuplicateDropForeignKeySql()
    {
        $diff = new TableDiff('documents');

        // Create offices table
        $offices = new Table('offices');
        $offices->addColumn('id', 'integer');

        // Create documents table
        $documents = new Table('documents');
        $documents->addColumn('id', 'integer');
        $documents->addColumn('office_id', 'integer');
        $documents->addForeignKeyConstraint('offices', [ 'office_id' ], [ 'id' ], [ 'onDelete' => 'CASCADE' ], 'FK_a788692ffa0c224');
        $documents->setPrimaryKey([ 'id' ]);

        // Added foreign key
        $diff->addedForeignKeys['FK_a788692ffa0c224'] = $documents->getForeignKey('FK_a788692ffa0c224');

        // Set removed foreign keys
        $documents = new Table('documents');
        $documents->addColumn('id', 'integer');
        $documents->addColumn('office_id', 'integer');
        $documents->setPrimaryKey([ 'id' ], 'PRIMARY');
        $documents->addIndex([ 'office_id' ], 'office_id');
        $documents->addForeignKeyConstraint('offices', [ 'office_id' ], [ 'id' ], [ 'onDelete' => 'RESTRICT', 'onUpdate' => 'RESTRICT' ], 'documents_ibfk_1');

        $diff->removedForeignKeys['documents_ibfk_1'] = $documents->getForeignKey('documents_ibfk_1');

        $diff->renamedIndexes = [
            'office_id' => new Index('IDX_A788692FFA0C224', [ 'office_id' ]),
        ];

        // Set fromTable
        $documents = new Table('documents', [
            new Column('id', Type::getType('integer')),
            new Column('office_id', Type::getType('integer')),
        ], [
            'primary'        => new Index('PRIMARY', [ 'id' ], true, true),
            'office_id'      => new Index('office_id', [ 'office_id' ]),
        ], [
            'jos_documents_ibfk_1' => new ForeignKeyConstraint([ 'office_id' ], 'offices', [ 'id' ], 'documents_ibfk_1', [ 'onDelete' => 'RESTRICT', 'onUpdate' => 'RESTRICT' ]),
        ]);

        $diff->fromTable = $documents;

        // Return diff
        return $diff;
    }

    /**
     * @group DBAL-2576
     */
    public function testNotDuplicateDropForeignKeySql()
    {
        $diff = $this->createNotDuplicateDropForeignKeySql();

        // Run through alter table method
        $sql = $this->_platform->getAlterTableSQL($diff);

        // Assert that there are no duplicates in the results
        $this->assertSame([
            'ALTER TABLE documents DROP FOREIGN KEY documents_ibfk_1',
            'ALTER TABLE documents ADD CONSTRAINT FK_a788692ffa0c224 FOREIGN KEY (office_id) REFERENCES offices (id) ON DELETE CASCADE',
            'DROP INDEX office_id ON documents',
            'CREATE INDEX IDX_A788692FFA0C224 ON documents (office_id)',
        ], $sql);
    }
}
