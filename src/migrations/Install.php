<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\migrations;

use craft\db\Migration;
use craft\helpers\Db;
use craft\helpers\MigrationHelper;

use abmat\checkit\records\EntryRecord;
use abmat\checkit\records\SectionRecord;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createTables();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTables();

		return true;
    }

    public function createTables(): void
    {
       $this->createTable(SectionRecord::$tableName, [
            'id' => $this->primaryKey(),
            'groupType' => $this->string(255),
            'groupId' => $this->integer(),
			'enabled' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

		$this->createTable(EntryRecord::$tableName, [
            'groupType' => $this->string(255)->notNull(),
            'entryId' => $this->integer(),
			'siteId' => $this->integer(),
			'ownerId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
			'PRIMARY KEY(groupType,entryId,siteId)',
        ]);
    }

    public function dropTables(): void
    {
        $this->dropTableIfExists(SectionRecord::$tableName);
        $this->dropTableIfExists(EntryRecord::$tableName);
    }
}
