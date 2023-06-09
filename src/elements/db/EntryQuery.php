<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\elements\db;

use craft\elements\db\EntryQuery as CraftEntryQuery;

class EntryQuery extends CraftEntryQuery {

	protected function beforePrepare(): bool
    {
		$this->query->innerJoin("abm_checkit_entries",'`abm_checkit_entries`.`groupType`="sections" and `abm_checkit_entries`.`siteId`=`elements_sites`.`siteId` and `abm_checkit_entries`.`entryId`=`elements`.`id`');
		
		return parent::beforePrepare();
	}
}