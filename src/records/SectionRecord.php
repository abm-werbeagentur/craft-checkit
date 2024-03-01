<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\records;

use craft\db\ActiveRecord;

/**
 * Class SectionRecord
 *
 * @package abmat\checkit\records
 */
class SectionRecord extends ActiveRecord
{
	public static $tableName = '{{%abm_checkit_sections}}';

	public static function tableName ()
	{
		return self::$tableName;
	}
}