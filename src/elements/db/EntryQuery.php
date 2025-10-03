<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\elements\db;

use craft\elements\db\EntryQuery as CraftEntryQuery;
use abmat\checkit\records\EntryRecord as CheckItEntryRecord;

class EntryQuery extends CraftEntryQuery {

	protected function afterPrepare(): bool
    {
        parent::afterPrepare();

		$this->subQuery->innerJoin(
			["abm_checkit_entries" => CheckItEntryRecord::tableName()],
			'[[abm_checkit_entries.groupType]]="sections" and
			[[abm_checkit_entries.siteId]]=[[elements_sites.siteId]] and
			[[abm_checkit_entries.entryId]]=[[elements.id]]');

		return true;
	}

	protected function cacheTags(): array
    {
        $tags = [];

        if ($this->typeId) {
            foreach ($this->typeId as $typeId) {
                $tags[] = "entryCheckIt:$typeId";
            }
        }

        return $tags;
    }
}