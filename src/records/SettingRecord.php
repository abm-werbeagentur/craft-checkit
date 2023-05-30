<?php
namespace abmat\checkit\records;

use craft\db\ActiveRecord;

/**
 * Class SettingRecord
 *
 * @package abmat\checkit\records
 */
class SettingRecord extends ActiveRecord
{
	public static $tableName = '{{%abm_checkit_settings}}';

	public static function tableName ()
	{
		return self::$tableName;
	}
}