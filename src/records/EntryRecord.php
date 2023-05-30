<?php
namespace abmat\checkit\records;

use craft\db\ActiveRecord;

/**
 * Class EntryRecord
 *
 * @package abmat\checkit\records
 */
class EntryRecord extends ActiveRecord
{
	public static $tableName = '{{%abm_checkit_entries}}';

	public static function tableName ()
	{
		return self::$tableName;
	}
}