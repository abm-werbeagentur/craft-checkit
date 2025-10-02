<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\elements\db;

use craft\commerce\elements\db\ProductQuery as CommerceProductQuery;

use abmat\checkit\records\EntryRecord as CheckItEntryRecord;

class ProductQuery extends CommerceProductQuery {

	protected function beforePrepare(): bool
    {
		parent::beforePrepare();

		$this->query->innerJoin(
			["abm_checkit_entries" => CheckItEntryRecord::tableName()],
			'[[abm_checkit_entries.groupType]]="productTypes" and
			[[abm_checkit_entries.siteId]]=[[elements_sites.siteId]] and
			[[abm_checkit_entries.entryId]]=[[commerce_products.id]]');

		return true;
	}

	protected function cacheTags(): array
    {
        $tags = [];

        if ($this->typeId) {
            foreach ($this->typeId as $typeId) {
                $tags[] = "productTypeCheckIt:$typeId";
            }
        }

        return $tags;
    }

}